<?php

namespace App\Http\Controllers;

use App\Exports\CaSubjectsExport;
use App\Exports\CaRosterExport;
use App\Models\CaScoreSetting;
use App\Models\Subject;
use App\Models\ContinuousAssessment;
use App\Models\Programme;
use App\Models\Semester;
use App\Models\Student;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ContinuousAssessmentController extends Controller
{
    use ScopesToTeacher;

    /**
     * List the subjects the authenticated user can enter CA scores for.
     */
    public function index(Request $request)
    {
        $subjects = $this->buildSubjectRows($request);

        $programmes = Programme::orderBy('name')->get();
        $semesters = Semester::orderBy('academic_year_id', 'desc')->orderBy('semester_number')->get();

        return view('continuous-assessment.index', compact('subjects', 'programmes', 'semesters'));
    }

    /**
     * Export the current filtered subject list to Excel.
     */
    public function exportExcel(Request $request)
    {
        $subjects = $this->buildSubjectRows($request);

        return Excel::download(new CaSubjectsExport($subjects), 'continuous-assessment-subjects.xlsx');
    }

    /**
     * Export the current filtered subject list to PDF.
     */
    public function exportPdf(Request $request)
    {
        $subjects = $this->buildSubjectRows($request);

        $pdf = PDF::loadView('continuous-assessment.export-pdf', compact('subjects'))->setPaper('a4', 'landscape');

        return $pdf->download('continuous-assessment-subjects.pdf');
    }

    /**
     * Build the filtered subject list shared by the on-screen index and both exports.
     */
    protected function buildSubjectRows(Request $request)
    {
        $query = $this->scopeToTeacher(
            Subject::with(['semester', 'teachers', 'programmes'])->orderBy('code')
        );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('programme_id')) {
            $query->whereHas('programmes', fn ($q) => $q->where('programmes.id', $request->programme_id));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        return $query->get();
    }

    /**
     * Show the CA roster for a single subject.
     */
    public function show(Subject $subject)
    {
        if ($this->isScopedTeacher() && !$subject->teachers()->where('teachers.id', $this->authTeacherId())->exists()) {
            abort(403);
        }

        $semester = $subject->semester;
        $rows = $this->buildRosterRows($subject, $semester);

        return view('continuous-assessment.show', compact('subject', 'semester', 'rows'));
    }

    /**
     * Export a subject's full CA roster (attendance, project, assignment, mid-semester, total)
     * to Excel.
     */
    public function exportRoster(Subject $subject)
    {
        if ($this->isScopedTeacher() && !$subject->teachers()->where('teachers.id', $this->authTeacherId())->exists()) {
            abort(403);
        }

        $rows = $this->buildRosterRows($subject, $subject->semester);

        return Excel::download(new CaRosterExport($rows), $subject->code . '-continuous-assessment.xlsx');
    }

    /**
     * Build the CA roster rows (student, scores, total) shared by the on-screen page and
     * its Excel export. The roster is every active student enrolled in the subject (see
     * Subject::students()), not a registration list.
     */
    protected function buildRosterRows(Subject $subject, ?Semester $semester)
    {
        $students = $subject->students()->get();
        $academicYearId = $semester?->academic_year_id;

        return $students->map(function ($student) use ($subject, $semester, $academicYearId) {
            $setting = CaScoreSetting::where('level', $student->level)->first();

            $ca = ContinuousAssessment::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->where('semester_id', $semester?->id)
                ->where('academic_year_id', $academicYearId)
                ->first();

            $attendanceScore = (float) ($ca->attendance_score ?? 0);

            $total = $attendanceScore
                + (float) ($ca->project_score ?? 0)
                + (float) ($ca->assignment_score ?? 0)
                + (float) ($ca->mid_semester_score ?? 0);

            return [
                'student' => $student,
                'ca' => $ca,
                'setting' => $setting,
                'attendance_score' => $attendanceScore,
                'total' => $total,
            ];
        })->sortBy(fn ($row) => $row['student']->full_name)->values();
    }

    /**
     * Save the submitted CA scores for a subject's roster.
     */
    public function store(Request $request, Subject $subject)
    {
        if ($this->isScopedTeacher() && !$subject->teachers()->where('teachers.id', $this->authTeacherId())->exists()) {
            abort(403);
        }

        $semester = $subject->semester;
        $academicYearId = $semester?->academic_year_id;
        $enrolledStudentIds = $subject->students()->pluck('id')->all();
        $scores = $request->input('scores', []);
        $errors = [];

        foreach ($scores as $studentId => $values) {
            $student = Student::find($studentId);

            if (!$student || !in_array((int) $studentId, $enrolledStudentIds, true)) {
                continue;
            }

            $setting = CaScoreSetting::where('level', $student->level)->first();
            $data = [];

            foreach ([
                'attendance' => 'attendance_score',
                'project' => 'project_score',
                'assignment' => 'assignment_score',
                'mid_semester' => 'mid_semester_score',
            ] as $inputKey => $column) {
                if (!isset($values[$inputKey]) || $values[$inputKey] === '') {
                    continue;
                }

                $value = (float) $values[$inputKey];
                $maxColumn = $inputKey === 'mid_semester' ? 'mid_semester_max' : "{$inputKey}_max";
                $max = $setting?->{$maxColumn};

                if ($value < 0 || ($max !== null && $value > (float) $max)) {
                    $errors[] = "{$student->full_name}: {$inputKey} score must be between 0 and " . ($max ?? 'N/A') . '.';
                    continue;
                }

                $data[$column] = $value;
            }

            if (!empty($data)) {
                ContinuousAssessment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'semester_id' => $semester?->id,
                        'academic_year_id' => $academicYearId,
                    ],
                    $data
                );
            }
        }

        if (!empty($errors)) {
            return redirect()->route('continuous-assessment.show', $subject)
                ->withErrors(['scores' => $errors])
                ->withInput();
        }

        return redirect()->route('continuous-assessment.show', $subject)
            ->with('success', 'CA scores saved successfully.');
    }
}

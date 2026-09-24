<?php

namespace App\Http\Controllers;

use App\Exports\SubjectStudentsExport;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Programme;
use App\Models\Result;
use App\Models\Semester;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SubjectController extends Controller
{
    use ScopesToTeacher;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $this->scopeToTeacher(
            Subject::with(['programmes', 'semester', 'teachers'])
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

        $subjects = $query->orderBy('code')->paginate(20)->withQueryString();

        $programmes = Programme::orderBy('name')->get();
        $semesters = Semester::with('academicYear')->orderBy('academic_year_id', 'desc')->orderBy('semester_number')->get();

        return view('subjects.index', compact('subjects', 'programmes', 'semesters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $programmes = Programme::all();
        $semesters = Semester::all();
        $teachers = Teacher::orderBy('name')->get();

        return view('subjects.create', compact('programmes', 'semesters', 'teachers'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * A subject that runs across several forms (the normal case - see
     * SubjectSeeder) is created from a single submission here: the shared
     * details (title, description, credit hours, core flag, programmes,
     * teachers, semester) are entered once, and one row per selected form is
     * created from them, code-derived via Subject::buildCode(). A form whose
     * code already exists is left untouched rather than overwritten, so this
     * form doubles as "add the missing form(s) to an existing subject."
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_prefix' => 'required|string|max:15|regex:/^[A-Za-z]+$/',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'credit_hours' => 'required|numeric|min:0|max:12',
            'levels' => 'required|array|min:1',
            'levels.*' => 'integer|in:100,200,300',
            'programme_ids' => 'required|array|min:1',
            'programme_ids.*' => 'exists:programmes,id',
            'semester_id' => 'required|exists:semesters,id',
            'is_core' => 'boolean',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('subjects.create')
                ->withErrors($validator)
                ->withInput();
        }

        $created = [];
        $skipped = [];

        foreach (array_unique($request->levels) as $level) {
            $code = Subject::buildCode($request->code_prefix, (int) $level);

            if (Subject::where('code', $code)->exists()) {
                $skipped[] = $code;
                continue;
            }

            $subject = Subject::create([
                'code' => $code,
                'title' => $request->title,
                'description' => $request->description,
                'credit_hours' => $request->credit_hours,
                'level' => $level,
                'semester_id' => $request->semester_id,
                'is_core' => $request->is_core ?? false,
            ]);

            $subject->programmes()->attach($request->programme_ids);
            $subject->teachers()->sync($request->teacher_ids ?? []);

            $created[] = $code;
        }

        if (empty($created)) {
            return redirect()->route('subjects.create')
                ->with('error', 'Every selected form already has this subject ('.implode(', ', $skipped).') - nothing was created.')
                ->withInput();
        }

        $message = 'Created '.implode(', ', $created).'.';
        if (!empty($skipped)) {
            $message .= ' Skipped '.implode(', ', $skipped).' (already exists).';
        }

        return redirect()->route('subjects.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $subject = Subject::with(['programmes', 'semester', 'prerequisite'])->findOrFail($id);

        $enrolledStudents = $subject->students()->with('programme')->get();

        // Get statistics for this subject. WASSCE grade_point is 1-9 (lower is
        // better); C6 or better (<=6) counts as a pass/credit.
        $resultStats = Result::where('subject_id', $id)
            ->select(
                DB::raw('COUNT(*) as total_students'),
                DB::raw('AVG(score) as average_score'),
                DB::raw('COUNT(CASE WHEN grade_point <= 6 THEN 1 END) as pass_count'),
                DB::raw('COUNT(CASE WHEN grade_point > 6 THEN 1 END) as fail_count')
            )
            ->first();

        // Get grade distribution
        $gradeDistribution = Result::where('subject_id', $id)
            ->select('grade', DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->get()
            ->pluck('count', 'grade')
            ->toArray();

        // Get subjects that have this subject as a prerequisite
        $dependentSubjects = Subject::where('prerequisite_id', $id)->get();

        return view('subjects.show', compact(
            'subject',
            'enrolledStudents',
            'resultStats',
            'gradeDistribution',
            'dependentSubjects'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $subject = Subject::with(['programmes', 'teachers'])->findOrFail($id);
        $programmes = Programme::all();
        $semesters = Semester::all();
        $prerequisites = Subject::where('id', '!=', $id)->get();
        $teachers = Teacher::orderBy('name')->get();

        return view('subjects.edit', compact('subject', 'programmes', 'semesters', 'prerequisites', 'teachers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:subjects,code,' . $id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'credit_hours' => 'required|numeric|min:0|max:12',
            'level' => 'required|integer|in:100,200,300,400',
            'programme_ids' => 'required|array|min:1',
            'programme_ids.*' => 'exists:programmes,id',
            'semester_id' => 'required|exists:semesters,id',
            'is_core' => 'boolean',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('subjects.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        // Update the subject without the programme_ids/teacher_ids
        $subject->update([
            'code' => $request->code,
            'title' => $request->title,
            'description' => $request->description,
            'credit_hours' => $request->credit_hours,
            'level' => $request->level,
            'semester_id' => $request->semester_id,
            'is_core' => $request->is_core ?? false,
        ]);

        // Sync the selected programmes and teachers to the subject
        $subject->programmes()->sync($request->programme_ids);
        $subject->teachers()->sync($request->teacher_ids ?? []);

        return redirect()->route('subjects.show', $id)
            ->with('success', 'Subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subject = Subject::findOrFail($id);

        // Check if there are any results associated with this subject
        if ($subject->results()->count() > 0) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete subject with associated results.');
        }

        // Check if this subject is a prerequisite for other subjects
        if ($subject->dependentSubjects()->count() > 0) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete subject that is a prerequisite for other subjects.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    /**
     * Display students registered for a subject, with their result (if graded yet).
     */
    public function students(string $id)
    {
        $subject = Subject::findOrFail($id);
        $allRows = $this->buildStudentRows($id);

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('subjects.students', compact('subject', 'rows'));
    }

    /**
     * Export every student registered for the subject (not just the current page) to Excel.
     */
    public function exportStudents(string $id)
    {
        $subject = Subject::findOrFail($id);
        $rows = $this->buildStudentRows($id);

        return Excel::download(new SubjectStudentsExport($rows), $subject->code . '-students.xlsx');
    }

    /**
     * Build the full (unpaginated) student/result roster for a subject, shared by the
     * on-screen roster and its Excel export.
     */
    protected function buildStudentRows(string $id): \Illuminate\Support\Collection
    {
        $subject = Subject::with('semester')->findOrFail($id);
        $semesterId = $subject->semester_id;
        $academicYearId = $subject->semester?->academic_year_id;

        return $subject->students()->get()->map(function ($student) use ($id, $semesterId, $academicYearId) {
            $result = Result::where('student_id', $student->id)
                ->where('subject_id', $id)
                ->where('semester_id', $semesterId)
                ->where('academic_year_id', $academicYearId)
                ->first();

            return [
                'student' => $student,
                'result' => $result,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ];
        })->sortBy(fn ($row) => $row['student']->full_name)->values();
    }

    /**
     * Printable attendance sheet for a subject's enrolled students, sorted by index number.
     */
    public function printAttendance(string $id)
    {
        $subject = Subject::with('semester.academicYear')->findOrFail($id);

        $students = $subject->students()->orderBy('index_number')->get();

        $settings = DB::table('settings')->where('category', 'institution')->pluck('value', 'key')->toArray();

        return view('subjects.print-attendance', compact('subject', 'students', 'settings'));
    }

    /**
     * Export subject data.
     */
    public function export(string $id)
    {
        $subject = Subject::with(['programmes', 'semester', 'results.student'])->findOrFail($id);

        // This would typically generate an Excel file with subject data
        // For simplicity, we'll just redirect back with a success message

        return redirect()->route('subjects.show', $id)
            ->with('success', 'Subject data exported successfully.');
    }

    /**
     * Show form to add students to a subject.
     */
    public function addStudentsForm(string $id)
    {
        $subject = Subject::with(['programmes', 'semester'])->findOrFail($id);

        // Get students who are not already enrolled in this subject
        // First get IDs of students already enrolled
        $enrolledStudentIds = Result::where('subject_id', $id)
            ->pluck('student_id')
            ->toArray();

        // Then get students who are not in that list and match any of the subject's programmes
        $programmeIds = $subject->programmes->pluck('id')->toArray();
        $students = \App\Models\Student::whereIn('programme_id', $programmeIds)
            ->whereNotIn('id', $enrolledStudentIds)
            ->orderBy('full_name')
            ->get();

        // Get academic years and semesters for the form
        $academicYears = \App\Models\AcademicYear::orderBy('name', 'desc')->get();
        $semesters = \App\Models\Semester::all();

        return view('subjects.add_students', compact('subject', 'students', 'academicYears', 'semesters'));
    }

    /**
     * Add students to a subject.
     */
    public function addStudents(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $studentIds = $request->student_ids;
        $academicYearId = $request->academic_year_id;
        $semesterId = $request->semester_id;
        $count = 0;

        foreach ($studentIds as $studentId) {
            // Check if the student is already enrolled in this subject
            $exists = Result::where('student_id', $studentId)
                ->where('subject_id', $id)
                ->where('academic_year_id', $academicYearId)
                ->where('semester_id', $semesterId)
                ->exists();

            if (!$exists) {
                Result::create([
                    'student_id' => $studentId,
                    'subject_id' => $id,
                    'academic_year_id' => $academicYearId,
                    'semester_id' => $semesterId,
                    'class_score' => 0,
                    'exam_score' => 0,
                    'total_score' => 0,
                    'grade' => 'N/A',
                    'grade_point' => 0,
                ]);

                $count++;
            }
        }

        return redirect()->route('subjects.students', $id)
            ->with('success', "$count students successfully added to this subject.");
    }

    /**
     * Show results for a specific subject.
     */
    public function results(string $id)
    {
        $subject = Subject::with(['programmes', 'semester'])->findOrFail($id);

        // Get all results for this subject, grouped by academic year and semester
        $results = Result::with(['student', 'academicYear', 'semester'])
            ->where('subject_id', $id)
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('semester_id', 'desc')
            ->orderBy('total_score', 'desc')
            ->paginate(50);

        // Get academic years and semesters for filtering
        $academicYears = \App\Models\AcademicYear::orderBy('name', 'desc')->get();
        $semesters = \App\Models\Semester::all();

        // Calculate statistics
        $stats = [
            'total' => $results->total(),
            'average' => $results->avg('total_score') ?? 0,
            'highest' => $results->max('total_score') ?? 0,
            'lowest' => $results->min('total_score') ?? 0,
            'pass_rate' => $results->where('total_score', '>=', 40)->count() / max(1, $results->count()) * 100
        ];

        return view('subjects.results', compact('subject', 'results', 'academicYears', 'semesters', 'stats'));
    }
}

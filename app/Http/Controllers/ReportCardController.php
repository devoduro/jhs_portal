<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Report cards, viewable two ways:
 *  - show(): public, no-login, only reachable via a signed URL (see
 *    GradeNotificationService, which mints the link texted to a parent) - the
 *    `signed` middleware on that route rejects any request whose signature is
 *    missing, tampered with, or expired.
 *  - adminShow()/bulkForm()/bulkPrint(): logged-in staff viewing/printing
 *    report cards directly from the admin panel, individually or for a
 *    whole class at once, gated by the normal manage-results|view-results
 *    permission like the rest of the results screens.
 */
class ReportCardController extends Controller
{
    public function show(Request $request, Student $student)
    {
        $data = $this->reportCardData($student, $request->integer('semester_id') ?: null);

        return view('report-card.public', $data + ['publicLink' => true]);
    }

    public function adminShow(Request $request, Student $student)
    {
        $data = $this->reportCardData($student, $request->integer('semester_id') ?: null);

        $semesters = Semester::orderBy('academic_year_id', 'desc')->orderBy('semester_number', 'desc')->get();

        return view('report-card.public', $data + [
            'publicLink' => false,
            'semesters' => $semesters,
            'selectedSemesterId' => $request->integer('semester_id') ?: null,
        ]);
    }

    /**
     * Self-service report card for the logged-in student: pick from the semesters they
     * actually have results in and generate that one. The student is resolved from the
     * authenticated session (never from a route parameter) so one student can never pull up
     * another's report by guessing an id.
     */
    public function studentShow(Request $request)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return redirect()->route('student.dashboard')->with('error', 'Student profile not found.');
        }

        // Label each report "Form X Semester Y (Academic Year)" - the form comes from the
        // subjects graded in that semester (subjects are level-specific rows, so every result
        // in a semester shares one level) rather than the student's current level, since a
        // student's form at the time of an old semester's results may differ from their form
        // today after promotion.
        $availableReports = $student->results()
            ->with(['subject', 'semester.academicYear'])
            ->get()
            ->groupBy('semester_id')
            ->map(function ($resultsInSemester) {
                $semester = $resultsInSemester->first()->semester;
                $level = $resultsInSemester->first()->subject->level ?? null;

                return [
                    'semester_id' => $semester->id,
                    'label' => ($level ? \App\Support\FormLabel::label($level) . ' ' : '') . $semester->name . ' (' . ($semester->academicYear->name ?? '') . ')',
                    'academic_year_id' => $semester->academic_year_id,
                    'semester_number' => $semester->semester_number,
                ];
            })
            ->sortByDesc(fn ($r) => [$r['academic_year_id'], $r['semester_number']])
            ->values();

        $selectedSemesterId = $request->integer('semester_id') ?: null;

        if ($selectedSemesterId && !$availableReports->contains('semester_id', $selectedSemesterId)) {
            $selectedSemesterId = null;
        }

        $data = $selectedSemesterId ? $this->reportCardData($student, $selectedSemesterId) : null;

        return view('student.report-card', [
            'student' => $student,
            'availableReports' => $availableReports,
            'selectedSemesterId' => $selectedSemesterId,
            'reportData' => $data,
        ]);
    }

    /**
     * Pick students to print report cards for - either an entire class at once,
     * or a hand-picked selection (e.g. only students with results so far).
     */
    public function bulkForm(Request $request)
    {
        $classGroups = ClassGroup::with('programme')->orderBy('level')->orderBy('name')->get();
        $semesters = Semester::orderBy('academic_year_id', 'desc')->orderBy('semester_number', 'desc')->get();

        $students = collect();
        if ($request->filled('class_group_id')) {
            $students = Student::where('class_group_id', $request->class_group_id)
                ->orderBy('full_name')
                ->get();
        }

        return view('report-card.bulk-form', compact('classGroups', 'semesters', 'students'));
    }

    /**
     * Render report cards for multiple students as one printable page (one
     * per page via CSS page-break), sourced either from an explicit list of
     * student_ids or every student in a given class_group_id.
     */
    public function bulkPrint(Request $request)
    {
        $semesterId = $request->integer('semester_id') ?: null;

        if ($request->filled('student_ids')) {
            $students = Student::whereIn('id', (array) $request->input('student_ids'))
                ->orderBy('full_name')
                ->get();
        } elseif ($request->filled('class_group_id')) {
            $students = Student::where('class_group_id', $request->class_group_id)
                ->orderBy('full_name')
                ->get();
        } else {
            $students = collect();
        }

        if ($students->isEmpty()) {
            return back()->with('error', 'No students selected to print report cards for.');
        }

        $cards = $students->map(fn (Student $student) => $this->reportCardData($student, $semesterId));

        return view('report-card.bulk', compact('cards'));
    }

    protected function reportCardData(Student $student, ?int $semesterId): array
    {
        $student->load('programme');

        $resultsQuery = $student->results()
            ->with(['subject', 'semester', 'academicYear'])
            ->orderBy('academic_year_id')
            ->orderBy('semester_id');

        if ($semesterId) {
            $resultsQuery->where('semester_id', $semesterId);
        }

        $results = $resultsQuery->get();

        $groupedResults = [];
        foreach ($results as $result) {
            $academicYearId = $result->academic_year_id;
            $resultSemesterId = $result->semester_id;

            if (!isset($groupedResults[$academicYearId])) {
                $groupedResults[$academicYearId] = [
                    'academic_year' => $result->academicYear,
                    'semesters' => [],
                ];
            }

            if (!isset($groupedResults[$academicYearId]['semesters'][$resultSemesterId])) {
                $groupedResults[$academicYearId]['semesters'][$resultSemesterId] = [
                    'semester' => $result->semester,
                    'results' => [],
                ];
            }

            $groupedResults[$academicYearId]['semesters'][$resultSemesterId]['results'][] = $result;
        }

        $aggregate = $student->calculateAggregate($semesterId);
        $gradeStanding = $student->getGradeStanding();

        $institutionSettings = DB::table('settings')
            ->where('category', 'institution')
            ->pluck('value', 'key')
            ->toArray();

        return compact('student', 'groupedResults', 'aggregate', 'gradeStanding', 'institutionSettings');
    }
}

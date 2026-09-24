<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\Programme;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $academicYears = AcademicYear::all();
        $programmes = Programme::all();
        $semesters = Semester::all();

        return view('reports.index', compact('academicYears', 'programmes', 'semesters'));
    }

    /**
     * Generate WASSCE aggregate/division distribution report for an academic year.
     */
    public function gradeDistribution(Request $request)
    {
        // Get data for filter dropdowns
        $academicYears = AcademicYear::all();
        $programmes = Programme::all();

        // If no filters are selected, just show the form
        if (!$request->has('academic_year_id')) {
            return view('reports.grade-distribution', compact('academicYears', 'programmes'));
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'programme_id' => 'nullable|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $academicYearId = $request->input('academic_year_id');
        $programmeId = $request->input('programme_id');

        $academicYear = AcademicYear::findOrFail($academicYearId);
        $programme = $programmeId ? Programme::findOrFail($programmeId) : null;

        // Get students in scope
        $studentsQuery = Student::query();

        if ($programmeId) {
            $studentsQuery->where('programme_id', $programmeId);
        }

        $students = $studentsQuery->get();

        // Bucket students into WASSCE divisions based on their aggregate for this academic year
        $distribution = $this->bucketByDivision($students, $academicYearId);

        $averageAggregate = $students
            ->map(fn (Student $student) => $student->calculateAggregate(null, $academicYearId))
            ->filter(fn ($aggregate) => $aggregate !== null)
            ->avg();

        return view('reports.grade-distribution', compact(
            'academicYear',
            'programme',
            'distribution',
            'averageAggregate'
        ));
    }

    /**
     * Generate subject performance report.
     */
    public function subjectPerformance(Request $request)
    {
        // Get data for filter dropdowns
        $academicYears = AcademicYear::all();
        $semesters = Semester::all();
        $programmes = Programme::all();

        // If no filters are selected, just show the form
        if (!$request->has('academic_year_id') || !$request->has('semester_id')) {
            return view('reports.subject-performance', compact('academicYears', 'semesters', 'programmes'));
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_id' => 'required|exists:semesters,id',
            'programme_id' => 'nullable|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $academicYearId = $request->input('academic_year_id');
        $semesterId = $request->input('semester_id');
        $programmeId = $request->input('programme_id');

        $academicYear = AcademicYear::findOrFail($academicYearId);
        $semester = Semester::findOrFail($semesterId);
        $programme = $programmeId ? Programme::findOrFail($programmeId) : null;

        // Get subjects
        $subjectsQuery = Subject::query();

        if ($programmeId) {
            $subjectsQuery->whereHas('programmes', fn ($q) => $q->where('programmes.id', $programmeId));
        }

        if ($semesterId) {
            $subjectsQuery->where('semester_id', $semesterId);
        }

        $subjects = $subjectsQuery->get();

        // Get performance data for each subject
        $subjectPerformance = [];

        foreach ($subjects as $subject) {
            $results = Result::where([
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ])->get();

            $totalStudents = $results->count();
            // WASSCE grade_point is 1-9 (lower is better); C6 or better (<=6) counts as a pass/credit.
            $passCount = $results->where('grade_point', '<=', 6)->count();
            $failCount = $totalStudents - $passCount;
            $passRate = $totalStudents > 0 ? ($passCount / $totalStudents) * 100 : 0;
            $averageScore = $results->avg('score');

            $subjectPerformance[] = [
                'subject' => $subject,
                'total_students' => $totalStudents,
                'pass_count' => $passCount,
                'fail_count' => $failCount,
                'pass_rate' => round($passRate, 2),
                'average_score' => round($averageScore, 2),
            ];
        }

        return view('reports.subject-performance', compact(
            'academicYear',
            'semester',
            'programme',
            'subjectPerformance'
        ));
    }

    /**
     * Generate student performance report.
     */
    public function studentPerformance(Request $request)
    {
        // Get data for filter dropdowns
        $academicYears = AcademicYear::all();
        $programmes = Programme::all();

        // If no filters are selected, just show the form
        if (!$request->has('academic_year_id') || !$request->has('programme_id')) {
            return view('reports.student-performance', compact('academicYears', 'programmes'));
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'programme_id' => 'required|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $academicYearId = $request->input('academic_year_id');
        $programmeId = $request->input('programme_id');

        $academicYear = AcademicYear::findOrFail($academicYearId);
        $programme = Programme::findOrFail($programmeId);

        // Get students in the programme
        $students = Student::where('programme_id', $programmeId)->get();

        // Get performance data for each student
        $studentPerformance = [];

        foreach ($students as $student) {
            $results = Result::where([
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
            ])->get();

            $totalSubjects = $results->count();
            // WASSCE grade_point is 1-9 (lower is better); C6 or better (<=6) counts as a pass/credit.
            $passedSubjects = $results->where('grade_point', '<=', 6)->count();
            $failedSubjects = $totalSubjects - $passedSubjects;
            $aggregate = $student->calculateAggregate(null, $academicYearId);

            $studentPerformance[] = [
                'student' => $student,
                'total_subjects' => $totalSubjects,
                'passed_subjects' => $passedSubjects,
                'failed_subjects' => $failedSubjects,
                'aggregate' => $aggregate,
            ];
        }

        // Sort by aggregate ascending (lower aggregate = better WASSCE performance);
        // students with no aggregate yet (fewer than 6 graded subjects) sort last.
        $studentPerformance = collect($studentPerformance)
            ->sortBy(fn ($row) => $row['aggregate'] ?? PHP_INT_MAX)
            ->values()->all();

        return view('reports.student-performance', compact(
            'academicYear',
            'programme',
            'studentPerformance'
        ));
    }

    /**
     * Generate programme statistics report.
     */
    public function programmeStatistics(Request $request)
    {
        // Get data for filter dropdowns
        $academicYears = AcademicYear::all();

        // If no filters are selected, just show the form
        if (!$request->has('academic_year_id')) {
            return view('reports.programme-statistics', compact('academicYears'));
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $academicYearId = $request->input('academic_year_id');
        $academicYear = AcademicYear::findOrFail($academicYearId);

        // Get all programmes
        $programmes = Programme::all();

        // Get statistics for each programme
        $programmeStats = [];

        foreach ($programmes as $programme) {
            $students = Student::where('programme_id', $programme->id)->count();

            $results = Result::where('academic_year_id', $academicYearId)
                ->join('students', 'results.student_id', '=', 'students.id')
                ->where('students.programme_id', $programme->id)
                ->get();

            // WASSCE grade_point is 1-9 (lower is better); C6 or better (<=6) counts as a pass/credit.
            $passCount = $results->where('grade_point', '<=', 6)->count();
            $totalResults = $results->count();
            $passRate = $totalResults > 0 ? ($passCount / $totalResults) * 100 : 0;
            $averageGpa = $results->avg('grade_point') ?: 0;

            $programmeStats[] = [
                'programme' => $programme,
                'students_count' => $students,
                'pass_rate' => round($passRate, 2),
                'average_gpa' => round($averageGpa, 2),
            ];
        }

        return view('reports.programme-statistics', compact(
            'academicYear',
            'programmeStats'
        ));
    }

    /**
     * Generate WASSCE aggregate/division distribution report (all-time, per student).
     */
    public function aggregateDistribution(Request $request)
    {
        // Get data for filter dropdowns
        $programmes = Programme::all();

        // If no filters are selected, just show the form
        if (!$request->has('programme_id')) {
            return view('reports.aggregate-distribution', compact('programmes'));
        }

        $validator = Validator::make($request->all(), [
            'programme_id' => 'required|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $programmeId = $request->input('programme_id');
        $programme = $programmeId ? Programme::findOrFail($programmeId) : null;

        // Get students
        $studentsQuery = Student::query();

        if ($programmeId) {
            $studentsQuery->where('programme_id', $programmeId);
        }

        $students = $studentsQuery->get();

        // Bucket students into WASSCE divisions based on their all-time aggregate
        $divisions = $this->bucketByDivision($students);

        return view('reports.aggregate-distribution', compact(
            'programme',
            'divisions'
        ));
    }

    /**
     * Generate semester comparison report.
     */
    public function semesterComparison(Request $request)
    {
        // Get data for filter dropdowns
        $academicYears = AcademicYear::all();
        $programmes = Programme::all();

        // If no filters are selected, just show the form
        if (!$request->has('academic_year_id')) {
            return view('reports.semester-comparison', compact('academicYears', 'programmes'));
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'programme_id' => 'nullable|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $academicYearId = $request->input('academic_year_id');
        $programmeId = $request->input('programme_id');

        $academicYear = AcademicYear::findOrFail($academicYearId);
        $programme = $programmeId ? Programme::findOrFail($programmeId) : null;

        // Get semesters for the academic year
        $semesters = Semester::where('academic_year_id', $academicYearId)->get();

        // Get performance data for each semester
        $semesterData = [];

        foreach ($semesters as $semester) {
            // Build the query
            $query = DB::table('results')
                ->join('students', 'results.student_id', '=', 'students.id')
                ->where([
                    'results.academic_year_id' => $academicYearId,
                    'results.semester_id' => $semester->id,
                ]);

            if ($programmeId) {
                $query->where('students.programme_id', $programmeId);
            }

            // WASSCE grade_point is 1-9 (lower is better); C6 or better (<=6) counts as a pass/credit.
            $averageGpa = $query->avg('results.grade_point');
            $passRate = $query->where('results.grade_point', '<=', 6)->count() / max(1, $query->count()) * 100;

            $semesterData[] = [
                'semester' => $semester,
                'average_gpa' => round($averageGpa, 2),
                'pass_rate' => round($passRate, 2),
            ];
        }

        return view('reports.semester-comparison', compact(
            'academicYear',
            'programme',
            'semesterData'
        ));
    }

    /**
     * Export report data to Excel.
     */
    public function exportToExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:grade_distribution,subject_performance,student_performance,aggregate_distribution,semester_comparison',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'programme_id' => 'nullable|exists:programmes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('reports.index')
                ->withErrors($validator)
                ->withInput();
        }

        $reportType = $request->input('report_type');
        $academicYearId = $request->input('academic_year_id');
        $semesterId = $request->input('semester_id');
        $programmeId = $request->input('programme_id');

        // This would typically generate an Excel file based on the report type
        // For simplicity, we'll just redirect back with a success message

        return redirect()->route('reports.index')
            ->with('success', 'Report exported successfully.');
    }

    /**
     * Bucket a set of students into WASSCE division counts (Division 1-4, plus
     * "Ungraded" for students with fewer than 6 graded subjects), based on each
     * student's best-6-subjects aggregate. Pass $academicYearId to scope each
     * student's aggregate to a specific academic year; omit for their all-time aggregate.
     * Shared by gradeDistribution() (per academic year) and aggregateDistribution()
     * (all-time).
     */
    private function bucketByDivision(\Illuminate\Support\Collection $students, ?int $academicYearId = null): array
    {
        $distribution = [
            'Division 1' => 0,
            'Division 2' => 0,
            'Division 3' => 0,
            'Division 4' => 0,
            'Ungraded' => 0,
        ];

        foreach ($students as $student) {
            $aggregate = $academicYearId
                ? $student->calculateAggregate(null, $academicYearId)
                : $student->calculateAggregate();

            $standing = match (true) {
                $aggregate === null => 'Ungraded',
                $aggregate <= 12 => 'Division 1',
                $aggregate <= 24 => 'Division 2',
                $aggregate <= 36 => 'Division 3',
                default => 'Division 4',
            };

            $distribution[$standing]++;
        }

        return $distribution;
    }
}

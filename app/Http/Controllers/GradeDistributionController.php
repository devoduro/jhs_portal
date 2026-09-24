<?php

namespace App\Http\Controllers;

use App\Models\Programme;
use App\Models\Student;
use Illuminate\Http\Request;

class GradeDistributionController extends Controller
{
    /**
     * WASSCE-style aggregate/division distribution report: buckets students into
     * Division 1 (aggregate 6-12), Division 2 (13-24), Division 3 (25-36),
     * Division 4 (37+), and an "Ungraded" bucket for students with fewer than
     * 6 graded subjects (calculateAggregate() returns null), based on each
     * student's best-6-subjects aggregate.
     */
    public function index(Request $request)
    {
        // Get all programmes for filter dropdown
        $programmes = Programme::orderBy('name')->get();

        $query = Student::query()->with('programme');

        // Apply programme filter
        if ($request->filled('programme_id')) {
            $query->where('programme_id', $request->programme_id);
        }

        $students = $query->get();

        // Calculate each student's WASSCE aggregate and division standing
        $students->each(function (Student $student) {
            $student->aggregate = $student->calculateAggregate();
            $student->grade_standing = $student->getGradeStanding();
        });

        // Calculate division distribution
        $distribution = [
            'Division 1' => 0,
            'Division 2' => 0,
            'Division 3' => 0,
            'Division 4' => 0,
            'Ungraded' => 0,
        ];

        foreach ($students as $student) {
            $distribution[$student->grade_standing ?? 'Ungraded']++;
        }

        // Calculate statistics (based only on students with a defined aggregate)
        $gradedStudents = $students->filter(fn ($student) => $student->aggregate !== null);

        $stats = [
            'total_students' => $students->count(),
            'graded_students' => $gradedStudents->count(),
            'average_aggregate' => $gradedStudents->avg('aggregate'),
            'best_aggregate' => $gradedStudents->min('aggregate'),
            'worst_aggregate' => $gradedStudents->max('aggregate'),
        ];

        return view('grade-distribution.index', [
            'students' => $students,
            'programmes' => $programmes,
            'distribution' => $distribution,
            'stats' => $stats,
        ]);
    }
}

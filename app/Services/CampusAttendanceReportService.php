<?php

namespace App\Services;

use App\Models\CampusAttendance;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CampusAttendanceReportService
{
    /**
     * Build a day-by-day campus gate register for the given students across a
     * date range: each day lists every student with their first check-in,
     * last check-out, and a status derived from those two events. Days with
     * no punches at all are flagged "No Record" so a house master can spot
     * students who never checked onto campus that day.
     *
     * @param Collection<int, Student> $students
     * @return array<string, array<int, array{student: Student, time_in: ?string, time_out: ?string, status: string}>>
     */
    public static function build(Collection $students, Carbon $from, Carbon $to): array
    {
        $studentIds = $students->pluck('id');

        $eventsByDateAndStudent = CampusAttendance::whereIn('student_id', $studentIds)
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('occurred_at')
            ->get()
            ->groupBy(fn (CampusAttendance $event) => $event->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $dayEvents) => $dayEvents->groupBy('student_id'));

        $report = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $dayEvents = $eventsByDateAndStudent->get($dateKey, collect());

            $rows = [];

            foreach ($students as $student) {
                $studentEvents = $dayEvents->get($student->id, collect());

                $firstIn = $studentEvents->firstWhere('direction', 'in');
                $lastOut = $studentEvents->where('direction', 'out')->last();

                if ($studentEvents->isEmpty()) {
                    $status = 'No Record';
                } elseif ($studentEvents->last()->direction === 'in') {
                    $status = 'On Campus';
                } else {
                    $status = 'Off Campus';
                }

                $rows[] = [
                    'student' => $student,
                    'time_in' => $firstIn?->occurred_at?->format('g:i A'),
                    'time_out' => $lastOut?->occurred_at?->format('g:i A'),
                    'status' => $status,
                ];
            }

            $report[$dateKey] = $rows;
        }

        return $report;
    }
}

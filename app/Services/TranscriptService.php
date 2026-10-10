<?php

namespace App\Services;

use App\Models\GradeScheme;
use App\Models\Result;
use App\Models\Student;
use App\Models\TermReport;
use App\Support\FormLabel;
use Illuminate\Support\Collection;

/**
 * A pupil's academic record across several classes (e.g. Basic 1 to Basic 6): for every
 * class the pupil was in, each subject's total for each term, the yearly average and grade,
 * the term averages with position in class, attendance and the promotion decision.
 *
 * A score belongs to the class level it was earned in (the class it was recorded under, or
 * the subject's level for older scores), so promotions never move old scores around.
 */
class TranscriptService
{
    /**
     * @return array{student: Student, years: Collection, levelsAvailable: array, overall: ?float}
     */
    public static function forStudent(Student $student, ?int $fromLevel = null, ?int $toLevel = null): array
    {
        $results = $student->results()
            ->with(['subject', 'semester.academicYear', 'classGroup'])
            ->get()
            ->each(fn (Result $r) => $r->setAttribute('earned_level', $r->classGroup?->level ?? $r->subject?->level));

        $levelsAvailable = $results->pluck('earned_level')->filter()->unique()->sort()->values()->all();

        $results = $results->filter(fn (Result $r) => $r->earned_level !== null
            && (!$fromLevel || $r->earned_level >= $fromLevel)
            && (!$toLevel || $r->earned_level <= $toLevel));

        $termReports = TermReport::where('student_id', $student->id)->get()->keyBy('semester_id');
        $classCache = [];

        $years = $results->groupBy('earned_level')->sortKeys()->map(function (Collection $levelResults, $level) use ($student, $termReports, &$classCache) {
            $terms = $levelResults->pluck('semester')->unique('id')->sortBy('semester_number')->values();
            $scheme = GradeScheme::forLevel((int) $level);

            $subjects = $levelResults->groupBy(fn ($r) => $r->subject->title ?? 'Unknown')->map(function (Collection $rows, $title) use ($terms, $scheme) {
                $byTerm = [];
                foreach ($terms as $term) {
                    $r = $rows->firstWhere('semester_id', $term->id);
                    $byTerm[$term->id] = $r ? (float) $r->score : null;
                }
                $scores = array_filter($byTerm, fn ($v) => $v !== null);
                $average = $scores ? round(array_sum($scores) / count($scores), 1) : null;
                $band = $average !== null ? $scheme?->bandForScore($average) : null;

                return [
                    'title' => $title,
                    'is_core' => (bool) $rows->first()->subject?->is_core,
                    'terms' => $byTerm,
                    'average' => $average,
                    'grade' => $band?->grade,
                    'remark' => $band?->remark,
                ];
            })->sortBy(fn ($s) => [$s['is_core'] ? 0 : 1, $s['title']])->values();

            $termSummaries = $terms->map(function ($term) use ($levelResults, $student, $termReports, &$classCache) {
                $termResults = $levelResults->where('semester_id', $term->id);
                $classGroup = $termResults->first()->classGroup ?? TerminalReportService::classFor($student, $term);
                $position = null;
                $roll = null;

                if ($classGroup) {
                    $key = $classGroup->id . '-' . $term->id;
                    $classCache[$key] ??= TerminalReportService::forClass($classGroup, $term);
                    $mine = $classCache[$key]['pupils']->first(fn ($p) => $p['student']->id === $student->id);
                    $position = $mine['position'] ?? null;
                    $roll = $classCache[$key]['roll'];
                }

                $report = $termReports->get($term->id);

                return [
                    'term' => $term,
                    'average' => $termResults->count() ? round($termResults->avg('score'), 1) : null,
                    'position' => $position,
                    'roll' => $roll,
                    'days_present' => $report?->days_present,
                    'attendance_days' => $term->attendance_days,
                    'conduct' => $report?->conduct,
                    'promoted_to' => $report?->promoted_to,
                ];
            });

            $className = $levelResults->pluck('classGroup.name')->filter()->first() ?? FormLabel::label((int) $level);
            $yearAverage = $termSummaries->pluck('average')->filter(fn ($a) => $a !== null);

            return [
                'level' => (int) $level,
                'label' => FormLabel::label((int) $level),
                'class' => $className,
                'academic_year' => $terms->first()?->academicYear?->name,
                'terms' => $terms,
                'subjects' => $subjects,
                'termSummaries' => $termSummaries,
                'average' => $yearAverage->count() ? round($yearAverage->avg(), 1) : null,
                'promoted_to' => $termSummaries->last()['promoted_to'] ?? null,
            ];
        })->values();

        $overall = $years->pluck('average')->filter(fn ($a) => $a !== null);

        return [
            'student' => $student,
            'years' => $years,
            'levelsAvailable' => $levelsAvailable,
            'overall' => $overall->count() ? round($overall->avg(), 1) : null,
        ];
    }
}

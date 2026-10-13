<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\ClassGroup;
use App\Models\GradeScheme;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TermReport;
use App\Support\FormLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds Ghanaian basic-school terminal reports for a class and term: each pupil's class
 * score, exam score and total per subject with their position in that subject, overall
 * total / average with position in class out of the number on roll, grade and remark from
 * the grading scheme for the class's section, the BECE-style aggregate for JHS, attendance,
 * conduct and remarks, and the pupil's fee position.
 *
 * Positions use standard competition ranking (ties share a position, the next is skipped:
 * 1st, 2nd, 2nd, 4th).
 */
class TerminalReportService
{
    public static function classScoreMax(): float
    {
        return (float) Setting::get('class_score_max', '50');
    }

    public static function examScoreMax(): float
    {
        return (float) Setting::get('exam_score_max', '50');
    }

    public static function ordinal(?int $number): string
    {
        if (!$number) {
            return '—';
        }

        $suffix = 'th';
        if (!in_array($number % 100, [11, 12, 13], true)) {
            $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$number % 10] ?? 'th';
        }

        return $number . $suffix;
    }

    /**
     * Competition ranking of a [key => value] map, highest value first.
     *
     * @return array<int|string, int> key => position
     */
    public static function rank(array $values): array
    {
        arsort($values);
        $positions = [];
        $position = 0;
        $previous = null;
        $index = 0;

        foreach ($values as $key => $value) {
            $index++;
            if ($previous === null || abs($value - $previous) > 0.0001) {
                $position = $index;
                $previous = $value;
            }
            $positions[$key] = $position;
        }

        return $positions;
    }

    /**
     * Pupils belonging to a class for a term: those whose results that term were recorded in
     * this class, plus the class's current active pupils (for the current term, before any
     * scores exist). For a past term only the recorded pupils count, so promotions since
     * then don't move pupils between old class lists.
     */
    public static function classRoll(ClassGroup $classGroup, Semester $term): Collection
    {
        $recordedIds = Result::where('semester_id', $term->id)
            ->where('class_group_id', $classGroup->id)
            ->distinct()
            ->pluck('student_id');

        $query = Student::with('classGroup')->where(function ($q) use ($classGroup, $recordedIds, $term) {
            $q->whereIn('id', $recordedIds);

            if ($term->is_current || $recordedIds->isEmpty()) {
                $q->orWhere(fn ($q2) => $q2->where('class_group_id', $classGroup->id)->where('status', 'active'));
            }
        });

        return $query->orderBy('full_name')->get();
    }

    /**
     * Subjects taught at a class level, core subjects first.
     */
    public static function subjectsFor(int $level): Collection
    {
        return Subject::where('level', $level)
            ->orderByDesc('is_core')
            ->orderBy('title')
            ->get();
    }

    /**
     * Save one subject's scores for a class. $scores = [student_id => [column key => mark]],
     * where the columns are the class level's score sheet (AssessmentComponent::sheetFor):
     * 'class_score' / 'exam_score' on the default sheet, or 'c<id>' per admin-defined
     * component. Component marks are summed into the pupil's class score and exam score, so
     * report cards and broadsheets work the same whichever sheet a level uses.
     *
     * A row with every mark blank removes that pupil's result - except a result recorded
     * before the level was given components (it has only totals), which is left untouched.
     */
    public static function saveScores(ClassGroup $classGroup, Subject $subject, Semester $term, array $scores): int
    {
        $columns = AssessmentComponent::sheetFor($classGroup->level);
        $usesComponents = $columns->contains(fn ($c) => $c['id'] !== null);
        $saved = 0;

        DB::transaction(function () use ($classGroup, $subject, $term, $scores, $columns, $usesComponents, &$saved) {
            foreach ($scores as $studentId => $row) {
                $marks = [];
                foreach ($columns as $column) {
                    $value = $row[$column['key']] ?? null;
                    $marks[$column['key']] = ($value === '' || $value === null) ? null : (float) $value;
                }

                $match = [
                    'student_id' => (int) $studentId,
                    'subject_id' => $subject->id,
                    'semester_id' => $term->id,
                    'academic_year_id' => $term->academic_year_id,
                ];

                if (collect($marks)->every(fn ($m) => $m === null)) {
                    $existing = Result::where($match)->first();

                    if ($existing && (!$usesComponents || $existing->componentScores()->exists())) {
                        $existing->delete();
                    }
                    continue;
                }

                // Sum the marks that count towards each of the two report-card scores; stays
                // null when nothing was entered towards it (a blank is not a zero).
                $sumFor = function (string $type) use ($columns, $marks) {
                    $entered = $columns->where('type', $type)->map(fn ($c) => $marks[$c['key']])->filter(fn ($m) => $m !== null);

                    return $entered->isEmpty() ? null : round($entered->sum(), 2);
                };
                $class = $sumFor(AssessmentComponent::TYPE_CLASS);
                $exam = $sumFor(AssessmentComponent::TYPE_EXAM);

                $total = round(($class ?? 0) + ($exam ?? 0), 2);
                $grading = GradeScheme::gradeFor($total, $classGroup->level);

                $result = Result::updateOrCreate($match, [
                    'class_group_id' => $classGroup->id,
                    'class_score' => $class,
                    'exam_score' => $exam,
                    'score' => $total,
                    'grade' => $grading['grade'] ?? '-',
                    'grade_point' => $grading['grade_point'] ?? 0,
                    'remark' => $grading['remark'],
                ]);

                if ($usesComponents) {
                    $keep = [];
                    foreach ($columns as $column) {
                        if ($marks[$column['key']] === null) {
                            continue;
                        }
                        $result->componentScores()->updateOrCreate(
                            ['assessment_component_id' => $column['id']],
                            ['score' => $marks[$column['key']]]
                        );
                        $keep[] = $column['id'];
                    }
                    $result->componentScores()->whereNotIn('assessment_component_id', $keep ?: [0])->delete();
                }

                $saved++;
            }
        });

        return $saved;
    }

    /**
     * Everything needed to print the terminal reports (or the broadsheet) for a class.
     *
     * @return array{classGroup: ClassGroup, term: Semester, subjects: Collection, pupils: Collection, roll: int}
     */
    public static function forClass(ClassGroup $classGroup, Semester $term, ?array $onlyStudentIds = null): array
    {
        $term->loadMissing('academicYear');
        $classGroup->loadMissing('classTeacher');

        $students = self::classRoll($classGroup, $term);
        $subjects = self::subjectsFor($classGroup->level);

        $results = Result::where('semester_id', $term->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        // Include any subject a pupil has a score in even if it isn't a level subject (e.g.
        // a subject later moved to another level), so no score silently disappears.
        $extraSubjectIds = $results->collapse()->pluck('subject_id')->unique()->diff($subjects->pluck('id'));
        if ($extraSubjectIds->isNotEmpty()) {
            $subjects = $subjects->concat(Subject::whereIn('id', $extraSubjectIds)->get());
        }

        // Subject positions: rank every pupil with a score in that subject.
        $subjectPositions = [];
        foreach ($subjects as $subject) {
            $scores = [];
            foreach ($results as $studentId => $studentResults) {
                $result = $studentResults->firstWhere('subject_id', $subject->id);
                if ($result && $result->score !== null) {
                    $scores[$studentId] = (float) $result->score;
                }
            }
            $subjectPositions[$subject->id] = self::rank($scores);
        }

        // Overall position by total score.
        $totals = [];
        foreach ($students as $student) {
            $studentResults = $results->get($student->id, collect());
            if ($studentResults->isNotEmpty()) {
                $totals[$student->id] = (float) $studentResults->sum('score');
            }
        }
        $overallPositions = self::rank($totals);
        $roll = $students->count();

        $termReports = TermReport::where('semester_id', $term->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $isJhs = FormLabel::isJhs($classGroup->level);
        $classAverage = count($totals) ? array_sum($totals) / count($totals) : null;

        $pupils = $students
            ->when($onlyStudentIds !== null, fn ($c) => $c->whereIn('id', $onlyStudentIds))
            ->map(function (Student $student) use ($subjects, $results, $subjectPositions, $overallPositions, $totals, $termReports, $isJhs, $term) {
                $studentResults = $results->get($student->id, collect());

                $rows = $subjects->map(function (Subject $subject) use ($studentResults, $subjectPositions, $student) {
                    $result = $studentResults->firstWhere('subject_id', $subject->id);

                    return [
                        'subject' => $subject,
                        'result' => $result,
                        'class_score' => $result?->class_score,
                        'exam_score' => $result?->exam_score,
                        'total' => $result?->score,
                        'grade' => $result?->grade,
                        'remark' => $result?->remark,
                        'position' => $subjectPositions[$subject->id][$student->id] ?? null,
                    ];
                })->values();

                $graded = $rows->filter(fn ($row) => $row['result']);
                $total = $totals[$student->id] ?? null;

                return [
                    'student' => $student,
                    'rows' => $rows,
                    'subjects_taken' => $graded->count(),
                    'total' => $total,
                    'average' => $graded->count() ? round($total / $graded->count(), 1) : null,
                    'position' => $overallPositions[$student->id] ?? null,
                    'aggregate' => $isJhs ? $student->calculateAggregate($term->id) : null,
                    'term_report' => $termReports->get($student->id),
                ];
            })
            ->values();

        return [
            'classGroup' => $classGroup,
            'term' => $term,
            'subjects' => $subjects,
            'pupils' => $pupils,
            'roll' => $roll,
            // Mean of the pupils' average percentages (not of their raw totals).
            'classAverage' => ($avgs = $pupils->pluck('average')->filter(fn ($a) => $a !== null))->isNotEmpty() ? round($avgs->avg(), 1) : null,
            'isJhs' => $isJhs,
        ];
    }

    /**
     * The class a pupil should be reported under for a term: the class their results that
     * term were recorded in, falling back to their current class.
     */
    public static function classFor(Student $student, Semester $term): ?ClassGroup
    {
        $classGroupId = Result::where('semester_id', $term->id)
            ->where('student_id', $student->id)
            ->whereNotNull('class_group_id')
            ->value('class_group_id');

        return $classGroupId ? ClassGroup::find($classGroupId) : $student->classGroup;
    }

    /**
     * Fee position printed at the foot of the report.
     */
    public static function feeSummary(Student $student, Semester $term): array
    {
        $termBill = $student->billedAmount(null, $term);
        $termPaid = $student->paidAmount(null, $term);
        $balance = $student->outstandingBalance();
        $next = $term->nextTerm();
        $nextTermFees = $next && $student->level
            ? (float) \App\Models\FeeStructure::where('semester_id', $next->id)
                ->where('level', $term->isFinalTerm() ? (FormLabel::next($student->level) ?? $student->level) : $student->level)
                ->sum('amount')
            : 0.0;

        return [
            'brought_forward' => $balance - ($termBill - $termPaid),
            'term_bill' => $termBill,
            'paid' => $termPaid,
            'balance' => $balance,
            'next_term_fees' => $nextTermFees,
        ];
    }
}

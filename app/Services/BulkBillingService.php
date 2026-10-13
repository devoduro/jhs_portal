<?php

namespace App\Services;

use App\Models\FeeCategory;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Billing one fee item (bus fee, excursion, uniform, a discount...) to many pupils at once:
 * the whole school, ticked classes, or hand-picked pupils. The same item can be a fixed amount
 * or a percentage of what the pupil is already billed for the term (e.g. 10% sibling discount),
 * and a plan is always worked out first so the bursar can review it before anything is saved.
 */
class BulkBillingService
{
    public const DISCOUNT_SLUG = 'discount';

    public const TARGET_ALL = 'all';
    public const TARGET_CLASSES = 'classes';
    public const TARGET_PUPILS = 'pupils';

    /**
     * Make sure the "Discount / Waiver" fee item exists and return its slug.
     */
    public static function discountCategory(): string
    {
        FeeCategory::firstOrCreate(['slug' => self::DISCOUNT_SLUG], ['name' => 'Discount / Waiver', 'is_protected' => false]);

        return self::DISCOUNT_SLUG;
    }

    /**
     * Active pupils matched by the chosen target.
     */
    public static function pupils(string $target, array $classGroupIds = [], array $studentIds = []): Collection
    {
        return Student::with('classGroup')
            ->where('status', 'active')
            ->when($target === self::TARGET_CLASSES, fn ($q) => $q->whereIn('class_group_id', $classGroupIds ?: [0]))
            ->when($target === self::TARGET_PUPILS, fn ($q) => $q->whereIn('id', $studentIds ?: [0]))
            ->orderBy('level')->orderBy('full_name')
            ->get();
    }

    /**
     * Work out what billing would do, without saving anything.
     *
     * $options: category (slug), mode ('fixed'|'percent'), amount, is_discount (bool),
     * base_category (percent of this fee item only; null = everything billed that term),
     * existing ('skip'|'overwrite').
     *
     * Each row: student, amount (signed, what would be billed), current (the amount already on
     * the pupil's bill for this item or null), action: add | update | skip | conflict | nothing.
     *
     * @return array{rows: Collection, count: int, total: float, skipped: int}
     */
    public static function plan(Semester $term, Collection $pupils, array $options): array
    {
        $category = $options['category'];
        $isPercent = ($options['mode'] ?? 'fixed') === 'percent';
        $sign = !empty($options['is_discount']) ? -1 : 1;
        $overwrite = ($options['existing'] ?? 'skip') === 'overwrite';
        $baseCategory = $options['base_category'] ?? null;

        $charges = StudentFeeCharge::where('semester_id', $term->id)
            ->whereIn('student_id', $pupils->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $rows = $pupils->map(function (Student $student) use ($charges, $category, $isPercent, $sign, $overwrite, $baseCategory, $options) {
            $own = $charges->get($student->id, collect());
            $existing = $own->firstWhere('category', $category);

            if ($isPercent) {
                $base = (float) $own->filter(fn ($c) => (float) $c->amount > 0 && $c->category !== $category
                    && ($baseCategory === null || $baseCategory === '' || $c->category === $baseCategory))->sum('amount');
                $amount = round($base * (float) $options['amount'] / 100, 2) * $sign;
            } else {
                $base = null;
                $amount = round((float) $options['amount'], 2) * $sign;
            }

            $action = 'add';
            if ($isPercent && abs($amount) < 0.005) {
                $action = 'nothing';
            } elseif ($existing && $existing->notes === 'Term bill') {
                // Never overwrite an item that came from the fee schedule; that is what
                // "Bill a Term" and the fee schedule are for.
                $action = 'conflict';
            } elseif ($existing) {
                $action = $overwrite ? 'update' : 'skip';
            }

            return [
                'student' => $student,
                'amount' => $amount,
                'base' => $base,
                'current' => $existing ? (float) $existing->amount : null,
                'action' => $action,
            ];
        });

        $applied = $rows->whereIn('action', ['add', 'update']);

        return [
            'rows' => $rows,
            'count' => $applied->count(),
            'total' => (float) $applied->sum('amount'),
            'skipped' => $rows->count() - $applied->count(),
        ];
    }

    /**
     * Save a plan produced by plan(): add or update the item on every pupil whose action says so.
     *
     * @return array{added: int, updated: int, total: float}
     */
    public static function apply(Semester $term, array $plan, string $category, ?string $notes): array
    {
        $stats = ['added' => 0, 'updated' => 0, 'total' => 0.0];

        DB::transaction(function () use ($term, $plan, $category, $notes, &$stats) {
            foreach ($plan['rows'] as $row) {
                if (!in_array($row['action'], ['add', 'update'], true)) {
                    continue;
                }

                StudentFeeCharge::updateOrCreate(
                    ['student_id' => $row['student']->id, 'semester_id' => $term->id, 'category' => $category],
                    ['academic_year_id' => $term->academic_year_id, 'amount' => $row['amount'], 'notes' => $notes, 'recorded_by' => Auth::id()]
                );

                $stats[$row['action'] === 'add' ? 'added' : 'updated']++;
                $stats['total'] += $row['amount'];
            }
        });

        return $stats;
    }
}

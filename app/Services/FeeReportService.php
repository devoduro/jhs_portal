<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\FeeCategory;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fee collection figures shared by the Collection Report and the dashboard charts, so both
 * always show the same numbers. Everything is computed with grouped queries, never per pupil.
 */
class FeeReportService
{
    /**
     * Billed vs collected for the term per class (plus "Not in a class"), with overall
     * outstanding balances, and the whole-school totals.
     *
     * @return array{rows: Collection, grand: array}
     */
    public static function byClass(Semester $term): array
    {
        $rows = collect();
        $grand = ['pupils' => 0, 'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0];

        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();
        $students = Student::where('status', 'active')->get(['id', 'class_group_id', 'level']);

        $sum = fn ($query) => $query->selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');
        $billed = $sum(StudentFeeCharge::where('semester_id', $term->id));
        $collected = $sum(StudentPayment::where('semester_id', $term->id));
        $allCharges = $sum(StudentFeeCharge::query());
        $allPaid = $sum(StudentPayment::query());
        $allArrears = $sum(StudentArrear::query());
        $balance = fn ($id) => (float) ($allCharges[$id] ?? 0) + (float) ($allArrears[$id] ?? 0) - (float) ($allPaid[$id] ?? 0);

        $groups = $classGroups->map(fn ($c) => ['label' => $c->name, 'level' => $c->level, 'id' => $c->id, 'ids' => $students->where('class_group_id', $c->id)->pluck('id')]);
        $unplaced = $students->whereNull('class_group_id')->pluck('id');
        if ($unplaced->isNotEmpty()) {
            $groups->push(['label' => 'Not in a class', 'level' => null, 'id' => null, 'ids' => $unplaced]);
        }

        foreach ($groups as $group) {
            if ($group['ids']->isEmpty()) {
                continue;
            }

            $b = $group['ids']->sum(fn ($id) => (float) ($billed[$id] ?? 0));
            $c = $group['ids']->sum(fn ($id) => (float) ($collected[$id] ?? 0));
            $o = $group['ids']->sum(fn ($id) => max(0, $balance($id)));

            $rows->push([
                'class' => $group['label'],
                'class_group_id' => $group['id'],
                'level' => $group['level'],
                'pupils' => $group['ids']->count(),
                'debtors' => $group['ids']->filter(fn ($id) => $balance($id) > 0.009)->count(),
                'billed' => $b,
                'collected' => $c,
                'outstanding' => $o,
                'percentage' => $b > 0 ? round($c / $b * 100, 1) : 0,
            ]);

            $grand['pupils'] += $group['ids']->count();
            $grand['billed'] += $b;
            $grand['collected'] += $c;
            $grand['outstanding'] += $o;
        }

        return ['rows' => $rows, 'grand' => $grand];
    }

    /**
     * What the term's bills are made of: total per fee item.
     */
    public static function byItem(Semester $term): Collection
    {
        $names = FeeCategory::options();

        return StudentFeeCharge::where('semester_id', $term->id)
            ->selectRaw('category, COUNT(DISTINCT student_id) as pupils, SUM(amount) as total')
            ->groupBy('category')->get()
            ->map(fn ($row) => ['item' => $names[$row->category] ?? ucfirst($row->category), 'pupils' => (int) $row->pupils, 'total' => (float) $row->total])
            ->sortByDesc('total')->values();
    }

    /**
     * How the term's payments came in: cash, mobile money, bank...
     */
    public static function byMethod(Semester $term): Collection
    {
        return StudentPayment::where('semester_id', $term->id)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')->get()
            ->map(fn ($row) => ['method' => StudentPayment::METHODS[$row->payment_method] ?? $row->payment_method, 'count' => (int) $row->count, 'total' => (float) $row->total])
            ->sortByDesc('total')->values();
    }

    /**
     * Money received per day between two dates (days without payments are left out).
     */
    public static function daily(Carbon $from, Carbon $to): Collection
    {
        return StudentPayment::whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('payment_date, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_date')->orderBy('payment_date')->get()
            ->map(fn ($row) => ['date' => Carbon::parse($row->payment_date), 'count' => (int) $row->count, 'total' => (float) $row->total]);
    }
}

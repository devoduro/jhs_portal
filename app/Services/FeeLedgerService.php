<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fees for a basic school are billed per term: the fee structure lists what each class level
 * pays for each fee item (tuition, feeding, PTA levy...) in a term, and "billing" a term
 * copies those amounts onto every pupil as StudentFeeCharge rows. The ledger is then simply
 * bills + arrears brought forward - payments, so a pupil's history never changes when they
 * are later promoted to a class with different fees.
 */
class FeeLedgerService
{
    /**
     * The fee items (for the current term) that apply to a pupil - shown as the fee schedule.
     */
    public static function scheduleFor(Student $student): Collection
    {
        $term = Semester::where('is_current', true)->first();

        return $term ? $student->applicableFeeItems($term) : collect();
    }

    /**
     * Bill a term to a set of pupils from the fee structure for their class level. Items a
     * pupil has already been billed for that term are skipped (so re-running after adding a
     * new pupil only bills the new pupil), unless $refresh is set, in which case existing
     * bills are updated to the current structure amounts.
     *
     * @param  array<int, string>  $categories  limit to these fee items (empty = all)
     * @return array{billed: int, updated: int, skipped: int, students: int, total: float}
     */
    public static function billTerm(Semester $term, Collection $students, array $categories = [], bool $refresh = false): array
    {
        $structures = FeeStructure::where('semester_id', $term->id)
            ->when($categories, fn ($q) => $q->whereIn('category', $categories))
            ->get()
            ->groupBy('level');

        $stats = ['billed' => 0, 'updated' => 0, 'skipped' => 0, 'students' => 0, 'total' => 0.0];

        DB::transaction(function () use ($term, $students, $structures, $refresh, &$stats) {
            foreach ($students as $student) {
                $items = $structures->get($student->level, collect());

                if ($items->isEmpty()) {
                    continue;
                }

                $stats['students']++;

                foreach ($items as $item) {
                    $existing = StudentFeeCharge::where('student_id', $student->id)
                        ->where('semester_id', $term->id)
                        ->where('category', $item->category)
                        ->first();

                    if ($existing && !$refresh) {
                        $stats['skipped']++;
                        continue;
                    }

                    if ($existing) {
                        $existing->update(['amount' => $item->amount, 'recorded_by' => Auth::id()]);
                        $stats['updated']++;
                    } else {
                        StudentFeeCharge::create([
                            'student_id' => $student->id,
                            'academic_year_id' => $term->academic_year_id,
                            'semester_id' => $term->id,
                            'category' => $item->category,
                            'amount' => $item->amount,
                            'notes' => 'Term bill',
                            'recorded_by' => Auth::id(),
                        ]);
                        $stats['billed']++;
                    }

                    $stats['total'] += (float) $item->amount;
                }
            }
        });

        return $stats;
    }

    /**
     * Fee position of many pupils at once with one grouped query per table (never per pupil):
     * what is owed overall now, and the given term's bill and payments.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{balance: float, term_bill: float, term_paid: float}>
     */
    public static function positions(Collection $studentIds, ?Semester $term = null): array
    {
        $sum = fn ($model, ?int $termId = null) => $model::whereIn('student_id', $studentIds)
            ->when($termId, fn ($q) => $q->where('semester_id', $termId))
            ->selectRaw('student_id, SUM(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $charges = $sum(\App\Models\StudentFeeCharge::class);
        $payments = $sum(\App\Models\StudentPayment::class);
        $arrears = $sum(\App\Models\StudentArrear::class);
        $termCharges = $term ? $sum(\App\Models\StudentFeeCharge::class, $term->id) : collect();
        $termPayments = $term ? $sum(\App\Models\StudentPayment::class, $term->id) : collect();

        $positions = [];
        foreach ($studentIds as $id) {
            $positions[$id] = [
                'balance' => (float) ($charges[$id] ?? 0) + (float) ($arrears[$id] ?? 0) - (float) ($payments[$id] ?? 0),
                'term_bill' => (float) ($termCharges[$id] ?? 0),
                'term_paid' => (float) ($termPayments[$id] ?? 0),
            ];
        }

        return $positions;
    }

    /**
     * What billing a term would do, per class, before anything is saved: pupils, how many still
     * have fee items to be billed, how many new bill items and how much money that adds.
     *
     * @param  array<int, string>  $categories  limit to these fee items (empty = all)
     * @return array{rows: Collection, new_items: int, new_total: float, pupils: int, no_fees: int}
     */
    public static function termPlan(Semester $term, array $categories = []): array
    {
        $structures = FeeStructure::where('semester_id', $term->id)
            ->when($categories, fn ($q) => $q->whereIn('category', $categories))
            ->get()
            ->groupBy('level');

        $pupils = Student::with('classGroup')->where('status', 'active')->whereNotNull('level')->get();
        $billed = StudentFeeCharge::where('semester_id', $term->id)
            ->get(['student_id', 'category'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('category')->all());

        $rows = [];
        $noFees = 0;

        foreach ($pupils as $pupil) {
            $items = $structures->get($pupil->level, collect());
            if ($items->isEmpty()) {
                $noFees++;
                continue;
            }

            $have = $billed->get($pupil->id, []);
            $missing = $items->reject(fn ($i) => in_array($i->category, $have, true));

            $key = $pupil->class_group_id ?? 'none';
            $rows[$key] ??= [
                'class' => $pupil->classGroup?->name ?? 'Not in a class',
                'level' => $pupil->level,
                'pupils' => 0, 'to_bill' => 0, 'items' => 0, 'amount' => 0.0,
            ];
            $rows[$key]['pupils']++;
            if ($missing->isNotEmpty()) {
                $rows[$key]['to_bill']++;
                $rows[$key]['items'] += $missing->count();
                $rows[$key]['amount'] += (float) $missing->sum('amount');
            }
        }

        $rows = collect($rows)->sortBy('level')->values();

        return [
            'rows' => $rows,
            'new_items' => (int) $rows->sum('items'),
            'new_total' => (float) $rows->sum('amount'),
            'pupils' => (int) $rows->sum('pupils'),
            'no_fees' => $noFees,
        ];
    }

    /**
     * Full statement of account, newest first, each row carrying the running balance.
     */
    public static function ledgerFor(Student $student): array
    {
        $payments = $student->payments()->with(['academicYear', 'semester'])->get();
        $arrears = $student->arrears()->with('academicYear')->get();
        $feeCharges = $student->feeCharges()->with(['academicYear', 'semester'])->get();

        $rows = collect();

        foreach ($arrears as $arrear) {
            $amount = (float) $arrear->amount;
            $yearName = $arrear->academicYear->name ?? 'N/A';

            // A negative arrear means the school owes the pupil (e.g. an overpayment).
            $rows->push([
                'date' => $arrear->created_at,
                'description' => $amount >= 0 ? "Balance brought forward - {$yearName}" : "Credit brought forward - {$yearName}",
                'debit' => $amount > 0 ? $amount : null,
                'credit' => $amount < 0 ? abs($amount) : null,
                'academic_year' => $yearName,
                'term' => null,
                'bank' => null,
                'payment_mode' => null,
            ]);
        }

        foreach ($feeCharges as $charge) {
            $termName = $charge->semester?->name;

            $rows->push([
                // Term bills are dated to the start of their term so they sort ahead of the
                // term's payments even when the bill was generated a few days late.
                'date' => $charge->semester?->start_date && $charge->semester->start_date->lt($charge->created_at)
                    ? $charge->semester->start_date
                    : $charge->created_at,
                'description' => $charge->categoryLabel() . ($termName ? " - {$termName}" : ''),
                'debit' => (float) $charge->amount >= 0 ? (float) $charge->amount : null,
                'credit' => (float) $charge->amount < 0 ? abs((float) $charge->amount) : null,
                'academic_year' => $charge->academicYear->name ?? 'N/A',
                'term' => $termName,
                'bank' => null,
                'payment_mode' => null,
            ]);
        }

        foreach ($payments as $payment) {
            $rows->push([
                'date' => $payment->payment_date,
                'description' => 'Payment Received' . ($payment->receipt_number ? " ({$payment->receipt_number})" : ''),
                'debit' => null,
                'credit' => (float) $payment->amount,
                'academic_year' => $payment->academicYear->name ?? 'N/A',
                'term' => $payment->semester?->name,
                'bank' => $payment->bank,
                'payment_mode' => $payment->methodLabel(),
                // Only payment rows are backed by an editable StudentPayment record.
                'payment_id' => $payment->id,
                'academic_year_id' => $payment->academic_year_id,
                'semester_id' => $payment->semester_id,
                'reference_number' => $payment->reference_number,
                'receipt_number' => $payment->receipt_number,
            ]);
        }

        $balance = 0.0;

        // By day; within a day, bills and balances brought forward come before payments, so a
        // receipt shows the balance after everything owed up to that day.
        $chronological = $rows->sortBy(fn ($row) => [$row['date']?->toDateString() ?? '', isset($row['payment_id']) ? 1 : 0, $row['payment_id'] ?? 0])
            ->values()
            ->map(function (array $row) use (&$balance) {
                $balance += $row['debit'] ?? 0;
                $balance -= $row['credit'] ?? 0;
                $row['balance'] = $balance;

                return $row;
            });

        return $chronological->reverse()->values()->all();
    }
}

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

        $chronological = $rows->sortBy(fn ($row) => [$row['date']?->timestamp ?? 0, $row['credit'] !== null ? 1 : 0])
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

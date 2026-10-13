<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Bulk fee payments from a spreadsheet - a bank statement, a MoMo statement, or the bursar's own
 * list - keyed by the pupil's admission number. Rows are checked one by
 * one: bad rows are reported and skipped, and a payment whose reference is already on that
 * pupil's account is treated as a duplicate so re-uploading the same statement is harmless.
 */
class FeePaymentImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected int $processed = 0;
    protected int $skipped = 0;
    protected int $duplicates = 0;
    protected float $total = 0.0;
    protected array $errors = [];

    /** Payment ids created (empty on a dry run). */
    protected array $created = [];

    public function __construct(protected bool $dryRun = false, protected ?Semester $defaultTerm = null)
    {
        $this->defaultTerm ??= Semester::where('is_current', true)->first();
    }

    public function collection(Collection $rows)
    {
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // heading row is line 1
            $key = trim((string) ($row['index_number'] ?? $row['admission_number'] ?? ''));
            $amount = $row['amount'] ?? null;

            if ($key === '' && ($amount === null || $amount === '')) {
                continue;
            }

            if ($key === '') {
                $this->fail($line, 'no admission number');
                continue;
            }

            $student = Student::where('index_number', $key)->first();
            if (!$student) {
                $this->fail($line, "pupil {$key} not found");
                continue;
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                $this->fail($line, "amount for {$key} must be a number above zero");
                continue;
            }

            $date = $this->parseDate($row['date'] ?? $row['payment_date'] ?? null);
            if (!$date) {
                $this->fail($line, "date for {$key} is missing or not a valid date");
                continue;
            }
            if ($date->isFuture()) {
                $this->fail($line, "date for {$key} is in the future");
                continue;
            }

            $term = $this->resolveTerm(trim((string) ($row['term'] ?? '')), trim((string) ($row['academic_year'] ?? '')), $date);
            if (!$term) {
                $this->fail($line, "term for {$key} could not be worked out - add a term/academic_year column or set a current term");
                continue;
            }

            $reference = trim((string) ($row['reference'] ?? ''));
            $reference = $reference !== '' ? $reference : null;

            // The same reference on the same pupil's account is the same payment.
            $dupeKey = $student->id . '|' . $reference;
            if ($reference !== null && (isset($seen[$dupeKey]) || StudentPayment::where('student_id', $student->id)->where('reference_number', $reference)->exists())) {
                $this->duplicates++;
                $this->skipped++;
                continue;
            }
            $seen[$dupeKey] = true;

            if (!$this->dryRun) {
                $payment = StudentPayment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $term->academic_year_id,
                    'semester_id' => $term->id,
                    'amount' => round((float) $amount, 2),
                    'payment_method' => $this->method((string) ($row['method'] ?? $row['payment_method'] ?? '')),
                    'bank' => trim((string) ($row['bank'] ?? '')) ?: null,
                    'reference_number' => $reference,
                    'payment_date' => $date,
                    'paid_by' => trim((string) ($row['paid_by'] ?? '')) ?: null,
                    'notes' => 'Bulk uploaded',
                    'recorded_by' => Auth::id(),
                ]);
                $this->created[] = $payment->id;
            }

            $this->processed++;
            $this->total += round((float) $amount, 2);
        }
    }

    private function fail(int $line, string $reason): void
    {
        $this->errors[] = "Row {$line}: {$reason}.";
        $this->skipped++;
    }

    /**
     * The term a payment counts against: the named term of the named year, else the term whose
     * dates contain the payment date, else the current term.
     */
    private function resolveTerm(string $termName, string $yearName, Carbon $date): ?Semester
    {
        if ($termName !== '' || $yearName !== '') {
            $query = Semester::query();
            if ($yearName !== '') {
                $year = AcademicYear::where('name', $yearName)->first();
                if (!$year) {
                    return null;
                }
                $query->where('academic_year_id', $year->id);
            }
            if ($termName !== '') {
                $query->where('name', 'like', $termName . '%');
            }

            return $query->first();
        }

        return Semester::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first()
            ?? $this->defaultTerm;
    }

    private function method(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return match (true) {
            $raw === '' => 'other',
            str_contains($raw, 'momo'), str_contains($raw, 'mobile'), str_contains($raw, 'mtn'), str_contains($raw, 'vodafone'), str_contains($raw, 'telecel'), str_contains($raw, 'airtel') => 'mobile_money',
            str_contains($raw, 'bank'), str_contains($raw, 'transfer'), str_contains($raw, 'deposit') => 'bank_transfer',
            str_contains($raw, 'cash') => 'cash',
            str_contains($raw, 'cheque'), str_contains($raw, 'check') => 'cheque',
            default => 'other',
        };
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getStats(): array
    {
        return [
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'duplicates' => $this->duplicates,
            'total' => $this->total,
            'errors' => $this->errors,
            'dry_run' => $this->dryRun,
        ];
    }
}

<?php

namespace App\Imports;

use App\Models\ClassGroup;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Bulk admission from a class list spreadsheet. Columns (headings, any order):
 *   full_name*, gender* (Male/Female, M/F, Boy/Girl), class* (class name, e.g. "Basic 4A"),
 *   admission_number (blank = next number), date_of_birth, parent_name, relationship,
 *   parent_phone, address
 *
 * Rows with an admission number that already exists update that pupil instead of failing,
 * so the same sheet can be re-uploaded after corrections.
 */
class StudentsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;
    public int $updated = 0;
    public array $errors = [];

    public function collection(Collection $rows)
    {
        $classes = ClassGroup::all()->keyBy(fn ($c) => mb_strtolower(trim($c->name)));
        $prefix = strtoupper(Setting::get('institution_code', 'SCH') ?: 'SCH') . '/' . now()->year . '/';
        $next = (Student::where('index_number', 'like', $prefix . '%')->pluck('index_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0) + 1;

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $name = trim((string) ($row['full_name'] ?? ''));
            $className = mb_strtolower(trim((string) ($row['class'] ?? '')));
            $gender = $this->gender($row['gender'] ?? '');

            if ($name === '') {
                continue;
            }

            if (!$gender) {
                $this->errors[] = "Row {$line} ({$name}): gender must be Male or Female.";
                continue;
            }

            $class = $classes->get($className);
            if (!$class) {
                $this->errors[] = "Row {$line} ({$name}): class \"" . ($row['class'] ?? '') . '" not found. Create it under Classes first.';
                continue;
            }

            $admissionNumber = trim((string) ($row['admission_number'] ?? ''));
            $data = [
                'full_name' => $name,
                'gender' => $gender,
                'class_group_id' => $class->id,
                'level' => $class->level,
                'date_of_birth' => $this->date($row['date_of_birth'] ?? null),
                'emergency_contact_name' => $row['parent_name'] ?? null,
                'emergency_contact_relationship' => $row['relationship'] ?? null,
                'emergency_contact_phone' => isset($row['parent_phone']) ? $this->phone($row['parent_phone']) : null,
                'parent_email' => filter_var(trim((string) ($row['parent_email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
                'address' => $row['address'] ?? null,
                'status' => 'active',
            ];

            $existing = $admissionNumber !== '' ? Student::where('index_number', $admissionNumber)->first() : null;

            if ($existing) {
                $existing->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));
                $this->updated++;
                continue;
            }

            Student::create($data + [
                'index_number' => $admissionNumber !== '' ? $admissionNumber : $prefix . str_pad((string) $next++, 4, '0', STR_PAD_LEFT),
            ]);
            $this->created++;
        }
    }

    private function gender($value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'male', 'm', 'boy', 'b' => 'Male',
            'female', 'f', 'girl', 'g' => 'Female',
            default => null,
        };
    }

    /**
     * Excel drops the leading zero of Ghanaian numbers stored as numbers (241234567).
     */
    private function phone($value): ?string
    {
        return \App\Support\Phone::clean((string) $value);
    }

    private function date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            $timestamp = strtotime(str_replace('/', '-', (string) $value));

            return $timestamp ? date('Y-m-d', $timestamp) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

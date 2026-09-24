<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrearsTemplateExport implements FromArray, WithHeadings
{
    /**
     * Sample rows to guide admins filling in the template.
     */
    public function array(): array
    {
        return [
            ['SCH/2026/0001', '2025/2026', 350.00, 'Unpaid Third Term fees'],
            ['SCH/2026/0002', '2025/2026', -120.00, 'Overpayment carried forward'],
        ];
    }

    /**
     * Column headings expected by ArrearsImport. reference_number is the student's 7-digit
     * bank reference number (see Student Reference Numbers), not their index number - it's
     * what the bank uses to identify the student, so it's what bank-sourced debtor lists use.
     */
    public function headings(): array
    {
        return ['admission_number', 'academic_year', 'amount', 'notes'];
    }
}

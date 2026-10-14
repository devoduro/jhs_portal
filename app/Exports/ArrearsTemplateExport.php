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
     * Column headings expected by ArrearsImport: the pupil's admission number, the academic year the
     * balance is owed from, the amount (negative = the school owes the pupil) and optional notes.
     */
    public function headings(): array
    {
        return ['admission_number', 'academic_year', 'amount', 'notes'];
    }
}

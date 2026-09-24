<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentFeeChargeTemplateExport implements FromArray, WithHeadings
{
    /**
     * Sample rows to guide admins filling in the template.
     */
    public function array(): array
    {
        return [
            ['SCH/2026/001', 'transport', 450.00, '2026/2027', 'First Term', 'Bus - Kasoa route'],
            ['SCH/2026/002', 'uniform', 180.00, '2026/2027', 'First Term', '2 sets'],
        ];
    }

    /**
     * Column headings expected by StudentFeeChargeImport. Category must be one of:
     * tuition, graduation, resit.
     */
    public function headings(): array
    {
        return ['index_number', 'category', 'amount', 'academic_year', 'term', 'notes'];
    }
}

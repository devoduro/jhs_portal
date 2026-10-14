<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClassListTemplateExport implements FromArray, WithHeadings
{
    /**
     * Sample rows to guide admins filling in the template.
     */
    public function array(): array
    {
        return [
            ['SCH/2026/0001', 'Basic 4A'],
            ['SCH/2026/0002', 'JHS 1 Gold'],
        ];
    }

    /**
     * Column headings expected by ClassListImport.
     */
    public function headings(): array
    {
        return ['index_number', 'class_name'];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HouseAssignmentTemplateExport implements FromArray, WithHeadings
{
    /**
     * Sample rows to guide admins filling in the template.
     */
    public function array(): array
    {
        return [
            ['2021SLE0012', 'Aggrey House'],
            ['2020SLE0045', 'Nkrumah House'],
        ];
    }

    /**
     * Column headings expected by HouseAssignmentImport.
     */
    public function headings(): array
    {
        return ['index_number', 'house'];
    }
}

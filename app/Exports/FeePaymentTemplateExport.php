<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FeePaymentTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['SCH/2026/0001', 500.00, now()->toDateString(), 'Mobile Money', 'MTN', 'MP261013.1234.A56789', 'Mr. Asante', ''],
            ['SCH/2026/0002', 350.00, now()->toDateString(), 'Cash', '', '', 'Mrs. Boateng', ''],
        ];
    }

    /**
     * index_number, amount and date are required. method (cash / mobile money / bank / cheque),
     * bank (e.g. MTN, GCB), reference (the MoMo / bank transaction id - also used to skip
     * duplicates), paid_by and term (e.g. "First Term"; left blank, the term is picked from the
     * payment date) are optional.
     */
    public function headings(): array
    {
        return ['index_number', 'amount', 'date', 'method', 'bank', 'reference', 'paid_by', 'term'];
    }
}

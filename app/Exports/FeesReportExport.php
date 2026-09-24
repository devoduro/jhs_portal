<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FeesReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Collection $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Class', 'Pupils', 'Pupils Owing', 'Billed (Term)', 'Collected (Term)', '% Collected', 'Total Outstanding'];
    }

    public function map($row): array
    {
        return [
            $row['class'],
            $row['pupils'],
            $row['debtors'],
            round($row['billed'], 2),
            round($row['collected'], 2),
            $row['percentage'] . '%',
            round($row['outstanding'], 2),
        ];
    }
}

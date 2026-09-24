<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentFeesExport implements FromCollection, WithHeadings, WithMapping
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
        return ['Admission No.', 'Pupil', 'Class', 'Balance b/f', 'Term Bill', 'Paid (Term)', 'Balance', 'Status', 'Parent/Guardian', 'Parent Phone'];
    }

    public function map($row): array
    {
        $student = $row['student'];

        return [
            $student->index_number,
            $student->full_name,
            $student->classGroup->name ?? \App\Support\FormLabel::label($student->level),
            round($row['brought_forward'], 2),
            round($row['term_bill'], 2),
            round($row['term_paid'], 2),
            round($row['balance'], 2),
            ['debtor' => 'Owing', 'creditor' => 'In credit', 'settled' => 'Paid'][$row['status']],
            $student->emergency_contact_name,
            $student->emergency_contact_phone,
        ];
    }
}

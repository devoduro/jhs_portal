<?php

namespace App\Exports;

use App\Support\FormLabel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClassRosterExport implements FromCollection, WithHeadings, WithMapping
{
    private int $row = 0;

    public function __construct(protected Collection $students, protected array $columns, protected array $labels)
    {
    }

    public function collection()
    {
        return $this->students;
    }

    public function headings(): array
    {
        return array_merge(['#', 'Full Name'], array_map(fn ($c) => $this->labels[$c], $this->columns));
    }

    public function map($student): array
    {
        $values = [
            'index_number' => $student->index_number,
            'gender' => $student->gender,
            'date_of_birth' => $student->date_of_birth?->format('d/m/Y'),
            'parent' => $student->emergency_contact_name,
            'phone' => $student->emergency_contact_phone,
            'house' => $student->house?->name,
            'signature' => '',
            'remarks' => '',
        ];

        return array_merge([++$this->row, $student->full_name], array_map(fn ($c) => $values[$c] ?? '', $this->columns));
    }
}

<?php

namespace App\Exports;

use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CaSubjectsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Collection $subjects)
    {
    }

    public function collection()
    {
        return $this->subjects;
    }

    public function headings(): array
    {
        return ['Code', 'Title', 'Level', 'Programme', 'Semester', 'Teacher'];
    }

    public function map($subject): array
    {
        /** @var Subject $subject */
        return [
            $subject->code,
            $subject->title,
            $subject->level ?? 'N/A',
            $subject->programmes->pluck('code')->join(', ') ?: 'None',
            $subject->semester->name ?? 'N/A',
            $subject->teachers->pluck('name')->join(', ') ?: 'Not assigned',
        ];
    }
}

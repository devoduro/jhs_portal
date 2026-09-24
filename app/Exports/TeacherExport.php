<?php

namespace App\Exports;

use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeacherExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Collection $teachers)
    {
    }

    public function collection()
    {
        return $this->teachers;
    }

    public function headings(): array
    {
        return ['Name', 'Staff ID', 'Email', 'Phone', 'Department', 'Subjects', 'Classes (Current Semester)', 'Workload (Credit Hours)'];
    }

    public function map($teacher): array
    {
        /** @var Teacher $teacher */
        return [
            $teacher->name,
            $teacher->staff_id ?? 'N/A',
            $teacher->email ?? 'N/A',
            $teacher->phone ?? 'N/A',
            $teacher->department->name ?? 'N/A',
            $teacher->subjects_count,
            $teacher->workload_classes,
            $teacher->workload_credit,
        ];
    }
}

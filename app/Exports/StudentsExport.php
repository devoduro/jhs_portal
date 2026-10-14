<?php

namespace App\Exports;

use App\Models\Student;
use App\Support\FormLabel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The pupil list as shown on the Pupils screen (same filters), one row per pupil.
 */
class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        return Student::with('classGroup')->matching($this->request)->orderBy('level')->orderBy('full_name')->get();
    }

    public function headings(): array
    {
        return [
            'Admission Number',
            'Full Name',
            'Gender',
            'Date of Birth',
            'Class',
            'Parent / Guardian',
            'Parent Phone',
            'Parent Email',
            'Address',
            'Status',
        ];
    }

    public function map($student): array
    {
        return [
            $student->index_number,
            $student->full_name,
            $student->gender,
            $student->date_of_birth?->format('d/m/Y'),
            $student->classGroup->name ?? FormLabel::label($student->level),
            $student->emergency_contact_name,
            $student->emergency_contact_phone,
            $student->parent_email,
            $student->address,
            ucfirst((string) $student->status),
        ];
    }
}

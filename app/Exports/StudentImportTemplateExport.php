<?php

namespace App\Exports;

use App\Models\ClassGroup;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank class-list template for StudentsImport, with two example rows using real class names.
 */
class StudentImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['full_name', 'gender', 'class', 'admission_number', 'date_of_birth', 'parent_name', 'relationship', 'parent_phone', 'parent_email', 'address'];
    }

    public function array(): array
    {
        $class = ClassGroup::orderBy('level')->value('name') ?? 'Basic 1A';

        return [
            ['MENSAH Kwame Asare', 'Male', $class, '', '2018-03-14', 'Mr. Kofi Mensah', 'Father', '0241234567', 'Adenta, Accra'],
            ['OWUSU Ama Serwaa', 'Female', $class, '', '2018-07-02', 'Mrs. Akosua Owusu', 'Mother', '0209876543', 'Madina, Accra'],
        ];
    }
}

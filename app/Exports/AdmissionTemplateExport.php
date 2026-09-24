<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdmissionTemplateExport implements FromArray, WithHeadings
{
    /**
     * Sample rows to guide admins filling in the template.
     */
    public function array(): array
    {
        return [
            ['1234567890', 'OSEI MARIN SERWAA', 'Female', '18', 'General Arts', 'Day'],
            ['1234567891', 'QUANSAH YVONNE AGYAPOMAA', 'Female', '16', 'Home Economics', 'Boarder'],
        ];
    }

    /**
     * Column headings expected by AdmissionImport. gender and
     * aggregate_score are optional but, like programme and boarding_status,
     * are usually already known from the GES CSSPS placement export - no
     * need to make the applicant re-enter them later.
     */
    public function headings(): array
    {
        return ['index_number', 'full_name', 'gender', 'aggregate_score', 'programme', 'boarding_status'];
    }
}

<?php

namespace App\Exports;

use App\Models\House;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CampusAttendanceExport implements FromArray, WithHeadings
{
    /**
     * @param array<string, array<int, array{student: \App\Models\Student, time_in: ?string, time_out: ?string, status: string}>> $report
     */
    public function __construct(protected array $report, protected ?House $house = null)
    {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report as $date => $dayRows) {
            foreach ($dayRows as $row) {
                $rows[] = [
                    $date,
                    $row['student']->index_number,
                    $row['student']->full_name,
                    $row['student']->house->name ?? 'Unassigned',
                    $row['time_in'] ?? '-',
                    $row['time_out'] ?? '-',
                    $row['status'],
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Index Number', 'Full Name', 'House', 'Time In', 'Time Out', 'Status'];
    }
}

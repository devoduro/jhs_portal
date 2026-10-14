<?php

namespace App\Services;

use App\Models\Student;
use PDF;

class StudentPdfExport
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function export()
    {
        $students = Student::with('classGroup')->matching($this->request)->orderBy('level')->orderBy('full_name')->get();

        $pdf = PDF::loadView('exports.students-pdf', ['students' => $students])->setPaper('a4', 'landscape');

        return $pdf->download('pupils.pdf');
    }
}

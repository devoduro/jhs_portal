<?php

namespace App\Http\Controllers;

use App\Services\FeeLedgerService;
use Illuminate\Support\Facades\Auth;

class StudentFeeController extends Controller
{
    /**
     * Show the authenticated student's fee schedule and full statement of account.
     */
    public function index()
    {
        $student = Auth::user()->student;

        $schedule = FeeLedgerService::scheduleFor($student);
        $ledger = FeeLedgerService::ledgerFor($student);

        $term = \App\Models\Semester::with('academicYear')->where('is_current', true)->first();
        $balanceDue = $ledger[0]['balance'] ?? 0.0;
        $termBill = $term ? $student->billedAmount(null, $term) : 0.0;
        $termPaid = $term ? $student->paidAmount(null, $term) : 0.0;
        $totalArrears = $balanceDue - ($termBill - $termPaid);
        $currentYearBalance = $termBill - $termPaid;

        return view('student.fees.index', compact(
            'student', 'schedule', 'ledger', 'totalArrears', 'currentYearBalance', 'balanceDue', 'term'
        ));
    }
}

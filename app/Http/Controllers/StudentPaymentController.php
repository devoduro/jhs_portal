<?php

namespace App\Http\Controllers;

use App\Exports\FeesReportExport;
use App\Exports\StudentFeesExport;
use App\Models\ClassGroup;
use App\Models\FeeCategory;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use App\Services\FeeLedgerService;
use App\Services\SmsReceiptService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

/**
 * Fee collection: the pupils' fee list for a term, each pupil's ledger (record payments,
 * print receipts and statements), and the term collection report.
 */
class StudentPaymentController extends Controller
{
    /** Pupils per fee status (all / owing / paid / in credit) from the last buildFeeRows() call. */
    private array $statusCounts = ['all' => 0, 'debtor' => 0, 'settled' => 0, 'creditor' => 0];

    public function index(Request $request)
    {
        [$term, $rows] = $this->buildFeeRows($request);

        // Searching for one pupil (e.g. from the dashboard "Find a pupil" box) goes straight to
        // their account, ready to receive a payment.
        if ($request->filled('search') && $rows->count() === 1 && !$request->filled('page')) {
            return redirect()->route('fees.show', ['student' => $rows->first()['student'], 'semester_id' => $term?->id]);
        }

        $terms = $this->terms();
        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();

        $summary = [
            'billed' => $rows->sum('term_bill'),
            'paid' => $rows->sum('term_paid'),
            'outstanding' => $rows->sum(fn ($r) => max($r['balance'], 0)),
            'debtors' => $rows->where('status', 'debtor')->count(),
            'pupils' => $rows->count(),
            'today' => (float) StudentPayment::whereDate('payment_date', today())->sum('amount'),
            'todayCount' => StudentPayment::whereDate('payment_date', today())->count(),
        ];
        $summary['rate'] = $summary['billed'] > 0 ? round($summary['paid'] / $summary['billed'] * 100, 1) : 0;
        $statusCounts = $this->statusCounts;

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $students = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('fees.payments.index', compact('students', 'term', 'terms', 'classGroups', 'summary', 'statusCounts'));
    }

    public function exportExcel(Request $request)
    {
        [$term, $rows] = $this->buildFeeRows($request);

        return Excel::download(new StudentFeesExport($rows), 'pupil-fees' . ($term ? '-' . \Illuminate\Support\Str::slug($term->full_name) : '') . '.xlsx');
    }

    public function exportPdf(Request $request)
    {
        [$term, $rows] = $this->buildFeeRows($request);
        $classGroup = $request->filled('class_group_id') ? ClassGroup::find($request->class_group_id) : null;

        $pdf = PDF::loadView('fees.payments.export-pdf', compact('rows', 'term', 'classGroup'))->setPaper('a4', 'landscape');

        return $pdf->download('pupil-fees' . ($term ? '-' . \Illuminate\Support\Str::slug($term->full_name) : '') . '.pdf');
    }

    /**
     * Rows for the fee list: per pupil, the balance brought forward into the term, the term's
     * bill, what was paid against the term, and the overall balance now. Computed with grouped
     * queries (one per table), never per pupil.
     *
     * @return array{0: ?Semester, 1: Collection}
     */
    protected function buildFeeRows(Request $request): array
    {
        $term = $this->selectedTerm($request);

        $query = Student::with('classGroup')->where('status', $request->input('pupil_status', 'active'));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('index_number', 'like', "%{$search}%"));
        }

        if ($request->filled('class_group_id')) {
            $query->where('class_group_id', $request->class_group_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $students = $query->orderBy('level')->orderBy('full_name')->get();
        $ids = $students->pluck('id');

        $positions = FeeLedgerService::positions($ids, $term);

        $rows = $students->map(function (Student $student) use ($positions) {
            $position = $positions[$student->id];
            $balance = $position['balance'];

            return [
                'student' => $student,
                'brought_forward' => $position['brought_forward'],
                'term_bill' => $position['term_bill'],
                'term_paid' => $position['term_paid'],
                'balance' => $balance,
                'status' => $balance > 0.009 ? 'debtor' : ($balance < -0.009 ? 'creditor' : 'settled'),
            ];
        });

        // Counts for the status tabs, before the status filter narrows the list.
        $this->statusCounts = [
            'all' => $rows->count(),
            'debtor' => $rows->where('status', 'debtor')->count(),
            'settled' => $rows->where('status', 'settled')->count(),
            'creditor' => $rows->where('status', 'creditor')->count(),
        ];

        if ($request->filled('status')) {
            $wanted = rtrim($request->status, 's');
            $rows = $rows->filter(fn (array $r) => $r['status'] === $wanted);
        }

        if ($request->input('sort') === 'balance') {
            $rows = $rows->sortByDesc('balance');
        } elseif ($request->input('sort') === 'paid') {
            $rows = $rows->sortByDesc('term_paid');
        }

        return [$term, $rows->values()];
    }

    /**
     * A pupil's fee account: summary for the selected term, the record-payment form and the
     * full statement.
     */
    public function show(Request $request, Student $student)
    {
        $student->load('classGroup');
        $terms = $this->terms();
        $term = $this->selectedTerm($request);

        $termItems = $term
            ? $student->feeCharges()->where('semester_id', $term->id)->get()
            : collect();
        $termBill = (float) $termItems->sum('amount');
        $termPaid = $term ? $student->paidAmount(null, $term) : 0.0;

        $ledger = FeeLedgerService::ledgerFor($student);
        $balanceDue = $ledger[0]['balance'] ?? 0.0;
        $broughtForward = $term ? $student->balanceBroughtForward($term) : 0.0;

        // Fee items set for the pupil's class this term that haven't been billed to them yet.
        $unbilledItems = $term
            ? $student->applicableFeeItems($term)->reject(fn ($item) => $termItems->contains('category', $item->category))
            : collect();

        return view('fees.payments.show', compact(
            'student', 'terms', 'term', 'termItems', 'termBill', 'termPaid',
            'ledger', 'balanceDue', 'broughtForward', 'unbilledItems'
        ));
    }

    /**
     * Printable statement of account, oldest transaction first.
     */
    public function printLedger(Student $student)
    {
        $student->load('classGroup');
        $ledger = FeeLedgerService::ledgerFor($student);
        $balanceDue = $ledger[0]['balance'] ?? 0.0;
        $ledger = array_reverse($ledger);

        $settings = $this->institutionSettings();

        return view('fees.payments.print-ledger', compact('student', 'ledger', 'balanceDue', 'settings'));
    }

    /**
     * Printable payment receipt (two copies on one A4 page: pupil's copy and office copy).
     */
    public function receipt(StudentPayment $payment)
    {
        $payment->load(['student.classGroup', 'semester.academicYear', 'recordedBy']);
        $student = $payment->student;

        // Balance straight after this payment: everything on the ledger up to and including it.
        $balanceAfter = collect(FeeLedgerService::ledgerFor($student))
            ->firstWhere('payment_id', $payment->id)['balance'] ?? $student->outstandingBalance();

        $settings = $this->institutionSettings();

        return view('fees.payments.receipt', compact('payment', 'student', 'balanceAfter', 'settings'));
    }

    public function store(Request $request, Student $student)
    {
        $data = $this->validatePayment($request);

        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }

        $term = Semester::find($data['semester_id']);

        $payment = $student->payments()->create($data + [
            'academic_year_id' => $term->academic_year_id,
            'recorded_by' => auth()->id(),
        ]);

        $message = 'Payment of GH₵ ' . number_format($payment->amount, 2) . ' recorded. Receipt ' . $payment->fresh()->receipt_number . '.';

        if ($request->boolean('send_sms')) {
            $sent = app(SmsReceiptService::class)->send($payment->fresh());
            $message .= $sent ? ' SMS receipt sent to parent.' : ' (SMS receipt could not be sent - check the parent\'s phone number and SMS settings.)';
        }

        return redirect()->route('fees.show', ['student' => $student->id, 'semester_id' => $term->id])
            ->with('success', $message)
            ->with('receipt_url', route('fees.receipt', $payment));
    }

    public function update(Request $request, StudentPayment $payment)
    {
        $data = $this->validatePayment($request, 'edit_payment_' . $payment->id);

        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }

        $term = Semester::find($data['semester_id']);
        $payment->update($data + ['academic_year_id' => $term->academic_year_id]);

        return redirect()->route('fees.show', ['student' => $payment->student_id, 'semester_id' => $term->id])
            ->with('success', 'Payment ' . $payment->receipt_number . ' updated.');
    }

    public function destroy(StudentPayment $payment)
    {
        $payment->delete();

        return back()->with('success', 'Payment ' . $payment->receipt_number . ' removed.');
    }

    /**
     * Text a balance reminder to the parents of the ticked pupils on the fee list.
     */
    public function sendReminders(Request $request, SmsReceiptService $sms)
    {
        abort_unless($request->user()->can('send-sms'), 403);

        if ($request->boolean('all_owing')) {
            // Everyone owing in the list as currently filtered (class, level, search...).
            [, $rows] = $this->buildFeeRows($request);
            $ids = $rows->where('status', 'debtor')->pluck('student.id')->all();

            if (!$ids) {
                return back()->with('error', 'Nobody in this list owes anything.');
            }
        } else {
            $request->validate(['student_ids' => 'required|array', 'student_ids.*' => 'exists:students,id']);
            $ids = $request->student_ids;
        }

        $sent = 0;
        $students = Student::whereIn('id', $ids)->get();

        foreach ($students as $student) {
            if ($sms->sendReminder($student)) {
                $sent++;
            }
        }

        $skipped = $students->count() - $sent;

        return back()->with($sent ? 'success' : 'error', "Reminder sent to {$sent} parent(s)." . ($skipped ? " {$skipped} not sent (no phone number, nothing owed, or SMS failed)." : ''));
    }

    private function validatePayment(Request $request, string $errorBag = 'default')
    {
        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:semesters,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:' . implode(',', array_keys(StudentPayment::METHODS)),
            'bank' => 'nullable|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'nullable|string|max:255',
            'paid_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, $errorBag)->withInput();
        }

        return $validator->safe()->only(['semester_id', 'amount', 'payment_method', 'bank', 'payment_date', 'reference_number', 'paid_by', 'notes']);
    }

    /**
     * Term collection report: billed vs collected per class, per fee item, payment methods,
     * and a day-by-day cash book for a date range.
     */
    public function report(Request $request)
    {
        $data = $this->reportData($request);
        $data['terms'] = $this->terms();
        $data['classLookup'] = ClassGroup::all()->keyBy('name');

        return view('fees.report', $data);
    }

    public function printReport(Request $request)
    {
        $data = $this->reportData($request);
        abort_unless($data['term'], 404, 'No term selected to print.');
        $data['settings'] = $this->institutionSettings();

        return view('fees.report-print', $data);
    }

    public function exportReport(Request $request)
    {
        $data = $this->reportData($request);

        return Excel::download(new FeesReportExport($data['rows']), 'fees-report' . ($data['term'] ? '-' . \Illuminate\Support\Str::slug($data['term']->full_name) : '') . '.xlsx');
    }

    protected function reportData(Request $request): array
    {
        $term = $this->selectedTerm($request);
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from) : ($term?->start_date ?? now()->startOfMonth());
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->to) : now();

        $rows = collect();
        $items = collect();
        $methods = collect();
        $grand = ['pupils' => 0, 'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0];

        if ($term) {
            ['rows' => $rows, 'grand' => $grand] = \App\Services\FeeReportService::byClass($term);
            $items = \App\Services\FeeReportService::byItem($term);
            $methods = \App\Services\FeeReportService::byMethod($term);
        }

        $daily = \App\Services\FeeReportService::daily($from, $to);

        $payments = StudentPayment::with(['student.classGroup', 'recordedBy'])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        return compact('term', 'from', 'to', 'rows', 'items', 'methods', 'daily', 'payments', 'grand');
    }

    private function terms(): Collection
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }

    private function selectedTerm(Request $request): ?Semester
    {
        if ($request->filled('semester_id')) {
            return Semester::with('academicYear')->find($request->semester_id);
        }

        return Semester::with('academicYear')->where('is_current', true)->first()
            ?? $this->terms()->first();
    }

    private function institutionSettings(): array
    {
        return DB::table('settings')->whereIn('category', ['institution', 'system'])
            ->orderByRaw("category = 'institution'")
            ->pluck('value', 'key')->toArray();
    }
}

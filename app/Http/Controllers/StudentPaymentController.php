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
    public function index(Request $request)
    {
        [$term, $rows] = $this->buildFeeRows($request);

        $terms = $this->terms();
        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();

        $summary = [
            'billed' => $rows->sum('term_bill'),
            'paid' => $rows->sum('term_paid'),
            'outstanding' => $rows->sum(fn ($r) => max($r['balance'], 0)),
            'debtors' => $rows->where('status', 'debtor')->count(),
        ];

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $students = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('fees.payments.index', compact('students', 'term', 'terms', 'classGroups', 'summary'));
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

        $sum = fn ($model, $column = null, $value = null) => $model::whereIn('student_id', $ids)
            ->when($column, fn ($q) => $q->where($column, $value))
            ->selectRaw('student_id, SUM(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $allCharges = $sum(StudentFeeCharge::class);
        $allPayments = $sum(StudentPayment::class);
        $allArrears = $sum(StudentArrear::class);
        $termCharges = $term ? $sum(StudentFeeCharge::class, 'semester_id', $term->id) : collect();
        $termPayments = $term ? $sum(StudentPayment::class, 'semester_id', $term->id) : collect();

        $rows = $students->map(function (Student $student) use ($allCharges, $allPayments, $allArrears, $termCharges, $termPayments) {
            $id = $student->id;
            $balance = (float) ($allCharges[$id] ?? 0) + (float) ($allArrears[$id] ?? 0) - (float) ($allPayments[$id] ?? 0);
            $termBill = (float) ($termCharges[$id] ?? 0);
            $termPaid = (float) ($termPayments[$id] ?? 0);

            return [
                'student' => $student,
                'brought_forward' => $balance - ($termBill - $termPaid),
                'term_bill' => $termBill,
                'term_paid' => $termPaid,
                'balance' => $balance,
                'status' => $balance > 0.009 ? 'debtor' : ($balance < -0.009 ? 'creditor' : 'settled'),
            ];
        });

        if ($request->filled('status')) {
            $wanted = rtrim($request->status, 's');
            $rows = $rows->filter(fn (array $r) => $r['status'] === $wanted);
        }

        if ($request->input('sort') === 'balance') {
            $rows = $rows->sortByDesc('balance');
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
        $broughtForward = $balanceDue - ($termBill - $termPaid);

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

        $request->validate(['student_ids' => 'required|array', 'student_ids.*' => 'exists:students,id']);

        $sent = 0;
        $students = Student::whereIn('id', $request->student_ids)->get();

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
        $daily = collect();
        $grand = ['pupils' => 0, 'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0];

        if ($term) {
            $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();
            $students = Student::where('status', 'active')->get(['id', 'class_group_id', 'level']);

            $billed = StudentFeeCharge::where('semester_id', $term->id)
                ->selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');
            $collected = StudentPayment::where('semester_id', $term->id)
                ->selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');

            // Overall outstanding (all terms + arrears) per pupil, for the "still owing" column.
            $allCharges = StudentFeeCharge::selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');
            $allPaid = StudentPayment::selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');
            $allArrears = StudentArrear::selectRaw('student_id, SUM(amount) as total')->groupBy('student_id')->pluck('total', 'student_id');

            $groups = $classGroups->map(fn ($c) => ['label' => $c->name, 'level' => $c->level, 'ids' => $students->where('class_group_id', $c->id)->pluck('id')]);
            $unplaced = $students->whereNull('class_group_id')->pluck('id');
            if ($unplaced->isNotEmpty()) {
                $groups->push(['label' => 'Not in a class', 'level' => null, 'ids' => $unplaced]);
            }

            foreach ($groups as $group) {
                if ($group['ids']->isEmpty()) {
                    continue;
                }

                $b = $group['ids']->sum(fn ($id) => (float) ($billed[$id] ?? 0));
                $c = $group['ids']->sum(fn ($id) => (float) ($collected[$id] ?? 0));
                $o = $group['ids']->sum(fn ($id) => max(0, (float) ($allCharges[$id] ?? 0) + (float) ($allArrears[$id] ?? 0) - (float) ($allPaid[$id] ?? 0)));
                $debtors = $group['ids']->filter(fn ($id) => (float) ($allCharges[$id] ?? 0) + (float) ($allArrears[$id] ?? 0) - (float) ($allPaid[$id] ?? 0) > 0.009)->count();

                $rows->push([
                    'class' => $group['label'],
                    'level' => $group['level'],
                    'pupils' => $group['ids']->count(),
                    'debtors' => $debtors,
                    'billed' => $b,
                    'collected' => $c,
                    'outstanding' => $o,
                    'percentage' => $b > 0 ? round($c / $b * 100, 1) : 0,
                ]);

                $grand['pupils'] += $group['ids']->count();
                $grand['billed'] += $b;
                $grand['collected'] += $c;
                $grand['outstanding'] += $o;
            }

            $categoryNames = FeeCategory::options();
            $items = StudentFeeCharge::where('semester_id', $term->id)
                ->selectRaw('category, COUNT(DISTINCT student_id) as pupils, SUM(amount) as total')
                ->groupBy('category')->get()
                ->map(fn ($row) => ['item' => $categoryNames[$row->category] ?? ucfirst($row->category), 'pupils' => $row->pupils, 'total' => (float) $row->total]);

            $methods = StudentPayment::where('semester_id', $term->id)
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')->get()
                ->map(fn ($row) => ['method' => StudentPayment::METHODS[$row->payment_method] ?? $row->payment_method, 'count' => $row->count, 'total' => (float) $row->total]);
        }

        $daily = StudentPayment::whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('payment_date, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_date')->orderBy('payment_date')->get()
            ->map(fn ($row) => ['date' => \Carbon\Carbon::parse($row->payment_date), 'count' => $row->count, 'total' => (float) $row->total]);

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

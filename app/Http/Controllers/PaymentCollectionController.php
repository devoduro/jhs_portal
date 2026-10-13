<?php

namespace App\Http\Controllers;

use App\Exports\FeePaymentTemplateExport;
use App\Imports\FeePaymentImport;
use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\FeeLedgerService;
use App\Services\SmsReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Receiving fees in bulk: a class payment sheet (the bursar's list of a class with a box for
 * each pupil's payment - for a busy morning at the window) and an upload of payments from a
 * bank or MoMo statement. Single payments are still taken on the pupil's own fee account.
 */
class PaymentCollectionController extends Controller
{
    public function sheet(Request $request)
    {
        $terms = $this->terms();
        $term = $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $classGroups = ClassGroup::orderBy('level')->orderBy('name')->get();
        $classGroup = $request->filled('class_group_id') ? $classGroups->firstWhere('id', (int) $request->class_group_id) : null;

        $rows = collect();
        if ($classGroup && $term) {
            $pupils = Student::where('status', 'active')->where('class_group_id', $classGroup->id)->orderBy('full_name')->get();
            $positions = FeeLedgerService::positions($pupils->pluck('id'), $term);

            $rows = $pupils->map(fn (Student $pupil) => ['student' => $pupil] + $positions[$pupil->id]);
        }

        $methods = StudentPayment::METHODS;

        return view('fees.collect.sheet', compact('terms', 'term', 'classGroups', 'classGroup', 'rows', 'methods'));
    }

    public function storeSheet(Request $request)
    {
        $data = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_group_id' => 'required|exists:class_groups,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:' . implode(',', array_keys(StudentPayment::METHODS)),
            'bank' => 'nullable|string|max:255',
            'rows' => 'required|array',
            'rows.*.amount' => 'nullable|numeric|min:0|max:1000000',
            'rows.*.reference' => 'nullable|string|max:255',
            'rows.*.paid_by' => 'nullable|string|max:255',
        ]);

        $term = Semester::findOrFail($data['semester_id']);

        // Only pupils on this class's roll can be paid for from its sheet.
        $allowed = Student::where('class_group_id', $data['class_group_id'])->pluck('full_name', 'id');
        $entries = collect($data['rows'])
            ->filter(fn ($row, $id) => isset($allowed[$id]) && isset($row['amount']) && (float) $row['amount'] > 0);

        if ($entries->isEmpty()) {
            return back()->withInput()->with('error', 'Type an amount against at least one pupil.');
        }

        $created = [];
        $duplicates = 0;

        DB::transaction(function () use ($entries, $data, $term, &$created, &$duplicates) {
            foreach ($entries as $studentId => $row) {
                $amount = round((float) $row['amount'], 2);
                $reference = trim((string) ($row['reference'] ?? '')) ?: null;

                // A double-click or a refresh of the confirmation must not receive the same money twice.
                $recent = StudentPayment::where('student_id', $studentId)
                    ->where('semester_id', $term->id)
                    ->where('amount', $amount)
                    ->whereDate('payment_date', $data['payment_date'])
                    ->where('recorded_by', auth()->id())
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->exists();

                if ($recent) {
                    $duplicates++;
                    continue;
                }

                $created[] = StudentPayment::create([
                    'student_id' => (int) $studentId,
                    'academic_year_id' => $term->academic_year_id,
                    'semester_id' => $term->id,
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'],
                    'bank' => $data['bank'] ?? null,
                    'reference_number' => $reference,
                    'payment_date' => $data['payment_date'],
                    'paid_by' => trim((string) ($row['paid_by'] ?? '')) ?: null,
                    'recorded_by' => auth()->id(),
                ]);
            }
        });

        $created = collect($created);
        $message = $created->count() . ' payment(s) recorded, GH₵ ' . number_format($created->sum('amount'), 2) . ' received.';

        if ($duplicates) {
            $message .= " {$duplicates} skipped - the same payment was just recorded.";
        }

        if ($request->boolean('send_sms') && $request->user()->can('send-sms')) {
            $sms = app(SmsReceiptService::class);
            $sent = $created->filter(fn (StudentPayment $p) => $sms->send($p->fresh(['student', 'semester'])))->count();
            $message .= " SMS receipts sent to {$sent} of {$created->count()} parent(s).";
        }

        return redirect()->route('fees.collect', ['semester_id' => $term->id, 'class_group_id' => $data['class_group_id']])
            ->with('success', $message)
            ->with('receipts', $created->map(fn (StudentPayment $p) => [
                'name' => $allowed[$p->student_id] ?? '',
                'amount' => (float) $p->amount,
                'number' => $p->fresh()->receipt_number,
                'url' => route('fees.receipt', $p),
            ])->all());
    }

    public function uploadForm(Request $request)
    {
        $payments = StudentPayment::with(['student.classGroup', 'semester'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$search}%")->orWhere('index_number', 'like', "%{$search}%"));
            })
            ->when($request->filled('uploaded'), fn ($q) => $q->where('notes', 'Bulk uploaded'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('fees.payments.upload', compact('payments'));
    }

    public function import(Request $request)
    {
        $request->validate(['excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        try {
            $import = new FeePaymentImport($request->boolean('dry_run'));
            Excel::import($import, $request->file('excel_file'));
        } catch (\Throwable $e) {
            return redirect()->route('fees.payments.upload')->with('error', 'Could not read that file: ' . $e->getMessage());
        }

        $stats = $import->getStats();
        $verb = $stats['dry_run'] ? 'would be recorded' : 'recorded';
        $message = "{$stats['processed']} payment(s) {$verb}, GH₵ " . number_format($stats['total'], 2) . ". {$stats['skipped']} row(s) skipped"
            . ($stats['duplicates'] ? " ({$stats['duplicates']} already on file)" : '') . '.';

        if ($stats['dry_run']) {
            $message = 'Check only - nothing was saved. ' . $message;
        }

        if ($stats['errors']) {
            $message .= ' Problems: ' . implode(' ', array_slice($stats['errors'], 0, 6)) . (count($stats['errors']) > 6 ? ' (+' . (count($stats['errors']) - 6) . ' more)' : '');
        }

        return redirect()->route('fees.payments.upload')->with($stats['errors'] || $stats['dry_run'] ? 'warning' : 'success', $message);
    }

    public function template()
    {
        return Excel::download(new FeePaymentTemplateExport, 'fee_payments_template.xlsx');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['payment_ids' => 'required|array|min:1', 'payment_ids.*' => 'integer|exists:student_payments,id']);

        $count = StudentPayment::whereIn('id', $request->payment_ids)->delete();

        return back()->with('success', "Removed {$count} payment(s).");
    }

    private function terms()
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }
}

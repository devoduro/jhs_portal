<?php

namespace App\Http\Controllers;

use App\Exports\StudentFeeChargeTemplateExport;
use App\Imports\StudentFeeChargeImport;
use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\StudentFeeCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class StudentFeeChargeController extends Controller
{
    /**
     * Everything billed to pupils - term bills from the fee schedule and items billed to one
     * pupil (bus fee, uniform, discounts) - with totals per fee item.
     */
    public function index(Request $request)
    {
        $terms = \App\Models\Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])->values();
        $term = $request->has('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $filtered = function () use ($request, $term) {
            $query = StudentFeeCharge::query();

            if ($term) {
                $query->where('semester_id', $term->id);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('index_number', 'like', "%{$search}%"));
            }
            if ($request->filled('class_group_id')) {
                $query->whereHas('student', fn ($q) => $q->where('class_group_id', $request->class_group_id));
            }
            if ($request->input('type') === 'term') {
                $query->where('notes', 'Term bill');
            } elseif ($request->input('type') === 'individual') {
                $query->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', 'Term bill'));
            } elseif ($request->input('type') === 'discount') {
                $query->where('amount', '<', 0);
            }

            return $query;
        };

        // Totals per fee item for the chips (before the item filter narrows the list).
        $categoryNames = FeeCategory::options();
        $byCategory = $filtered()->selectRaw('category, COUNT(*) as n, SUM(amount) as total')->groupBy('category')->get()
            ->map(fn ($row) => ['slug' => $row->category, 'name' => $categoryNames[$row->category] ?? ucfirst($row->category), 'count' => (int) $row->n, 'total' => (float) $row->total])
            ->sortByDesc('total')->values();

        $query = $filtered()->with(['student.classGroup', 'semester.academicYear', 'recordedBy']);
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $summary = [
            'total' => (float) (clone $query)->sum('amount'),
            'items' => (clone $query)->count(),
            'pupils' => (clone $query)->distinct('student_id')->count('student_id'),
            'discounts' => (float) (clone $query)->where('amount', '<', 0)->sum('amount'),
            'individual' => (clone $query)->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', 'Term bill'))->count(),
        ];

        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [25, 50, 100, 200], true)) {
            $perPage = 25;
        }

        $charges = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $categories = FeeCategory::orderBy('name')->get();
        $classGroups = \App\Models\ClassGroup::orderBy('level')->orderBy('name')->get();

        return view('fees.charges.index', compact('charges', 'categories', 'classGroups', 'terms', 'term', 'byCategory', 'summary'));
    }

    /**
     * Bill a one-off item (bus fee, uniform, excursion...) to a single pupil from their fee
     * account page. A negative amount records a discount/waiver against that item.
     */
    public function store(Request $request, \App\Models\Student $student)
    {
        $data = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'category' => 'required|in:' . implode(',', array_keys(FeeCategory::options())),
            'amount' => 'required|numeric|not_in:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $term = \App\Models\Semester::findOrFail($data['semester_id']);

        StudentFeeCharge::updateOrCreate(
            ['student_id' => $student->id, 'semester_id' => $term->id, 'category' => $data['category']],
            [
                'academic_year_id' => $term->academic_year_id,
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]
        );

        return redirect()->route('fees.show', ['student' => $student, 'semester_id' => $term->id])
            ->with('success', FeeCategory::options()[$data['category']] . ' of GH₵ ' . number_format($data['amount'], 2) . ' added to ' . $student->full_name . "'s {$term->name} bill.");
    }

    /**
     * Show the bulk-upload form.
     */
    public function uploadForm()
    {
        return view('fees.charges.upload');
    }

    /**
     * Handle the bulk upload of specific student fee charges.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('fees.charges.upload')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $import = new StudentFeeChargeImport();
            Excel::import($import, $request->file('excel_file'));

            $stats = $import->getStats();
            $message = "Processed {$stats['processed']} record(s), skipped {$stats['skipped']}.";

            if (!empty($stats['errors'])) {
                $message .= ' Issues: ' . implode(' | ', array_slice($stats['errors'], 0, 5));
                if (count($stats['errors']) > 5) {
                    $message .= ' (+' . (count($stats['errors']) - 5) . ' more)';
                }

                return redirect()->route('fees.charges.index')->with('warning', $message);
            }

            return redirect()->route('fees.charges.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('fees.charges.upload')
                ->with('error', 'Error importing fee charges: ' . $e->getMessage());
        }
    }

    /**
     * Download the fee charges upload template.
     */
    public function downloadTemplate()
    {
        return Excel::download(new StudentFeeChargeTemplateExport, 'student_fee_charges_template.xlsx');
    }

    /**
     * Remove a mistaken fee charge entry.
     */
    public function destroy(StudentFeeCharge $charge)
    {
        $label = $charge->categoryLabel() . ' for ' . ($charge->student->full_name ?? 'pupil');
        $charge->delete();

        return back()->with('success', "Removed {$label}.");
    }

    /**
     * Remove several selected fee charges at once.
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'charge_ids' => 'required|array|min:1',
            'charge_ids.*' => 'integer|exists:student_fee_charges,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Tick at least one item to remove.');
        }

        $count = StudentFeeCharge::whereIn('id', $request->charge_ids)->delete();

        return back()->with('success', "Removed {$count} billed item(s).");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\FeeCategory;
use App\Models\Semester;
use App\Models\Student;
use App\Services\BulkBillingService;
use Illuminate\Http\Request;

/**
 * Bill one item - a bus fee, excursion, uniform, or a discount/waiver - to many pupils at once
 * (whole school, ticked classes, or hand-picked pupils), reviewing the plan before it is saved.
 */
class BulkBillController extends Controller
{
    public function create(Request $request)
    {
        $terms = $this->terms();
        $term = $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $classGroups = ClassGroup::with(['students' => fn ($q) => $q->where('status', 'active')->orderBy('full_name')])
            ->orderBy('level')->orderBy('name')->get();

        $categories = FeeCategory::orderBy('name')->get()->reject(fn ($c) => $c->slug === BulkBillingService::DISCOUNT_SLUG);

        return view('fees.bulk.create', compact('terms', 'term', 'classGroups', 'categories'));
    }

    /**
     * Show who would be billed what, before anything is saved.
     */
    public function preview(Request $request)
    {
        [$term, $options, $pupils] = $this->resolve($request);

        if ($pupils->isEmpty()) {
            return back()->withInput()->with('error', 'No active pupils match that selection.');
        }

        $plan = BulkBillingService::plan($term, $pupils, $options);
        $category = $options['category'];
        $itemName = FeeCategory::options()[$category] ?? ucfirst($category);
        $input = $request->only(['semester_id', 'category', 'mode', 'amount', 'is_discount', 'base_category', 'existing', 'notes', 'target']);

        return view('fees.bulk.preview', [
            'term' => $term,
            'plan' => $plan,
            'itemName' => $itemName,
            'options' => $options,
            'input' => $input,
            'classGroupIds' => (array) $request->input('class_group_ids', []),
            'studentIds' => (array) $request->input('student_ids', []),
            'notes' => $request->input('notes'),
        ]);
    }

    public function store(Request $request)
    {
        [$term, $options, $pupils] = $this->resolve($request);

        $plan = BulkBillingService::plan($term, $pupils, $options);

        if ($plan['count'] === 0) {
            return redirect()->route('fees.bulk-bill', ['semester_id' => $term->id])
                ->with('error', 'Nothing to bill - every selected pupil is already billed this item, or has no term bill to take a percentage of.');
        }

        $stats = BulkBillingService::apply($term, $plan, $options['category'], $request->input('notes') ?: null);

        $itemName = FeeCategory::options()[$options['category']] ?? $options['category'];
        $message = "{$itemName}: {$stats['added']} pupil(s) billed" . ($stats['updated'] ? ", {$stats['updated']} updated" : '')
            . ' (total GH₵ ' . number_format($stats['total'], 2) . ") for {$term->full_name}.";

        return redirect()->route('fees.charges.index', ['semester_id' => $term->id, 'category' => $options['category']])->with('success', $message);
    }

    /**
     * Validate the form and turn it into the term, plan options and the matching pupils.
     *
     * @return array{0: Semester, 1: array, 2: \Illuminate\Support\Collection}
     */
    private function resolve(Request $request): array
    {
        $data = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'target' => 'required|in:' . implode(',', [BulkBillingService::TARGET_ALL, BulkBillingService::TARGET_CLASSES, BulkBillingService::TARGET_PUPILS]),
            'class_group_ids' => 'nullable|array',
            'class_group_ids.*' => 'exists:class_groups,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'is_discount' => 'nullable|boolean',
            'category' => 'nullable|string',
            'mode' => 'required|in:fixed,percent',
            'amount' => 'required|numeric|gt:0',
            'base_category' => 'nullable|string',
            'existing' => 'required|in:skip,overwrite',
            'notes' => 'nullable|string|max:255',
        ]);

        $request->validate([
            'amount' => $data['mode'] === 'percent' ? 'lte:100' : 'lte:1000000',
            'class_group_ids' => $data['target'] === BulkBillingService::TARGET_CLASSES ? 'required|array|min:1' : 'nullable',
            'student_ids' => $data['target'] === BulkBillingService::TARGET_PUPILS ? 'required|array|min:1' : 'nullable',
        ], [
            'class_group_ids.required' => 'Tick at least one class.',
            'student_ids.required' => 'Tick at least one pupil.',
            'amount.lte' => $data['mode'] === 'percent' ? 'A percentage cannot be more than 100.' : 'That amount is too large.',
        ]);

        $isDiscount = $request->boolean('is_discount');
        $categories = FeeCategory::options();

        if ($isDiscount) {
            $category = BulkBillingService::discountCategory();
        } else {
            $category = $data['category'] ?? '';
            abort_unless(array_key_exists($category, $categories), 422, 'Choose the fee item to bill.');
        }

        $baseCategory = $data['mode'] === 'percent' && !empty($data['base_category']) && array_key_exists($data['base_category'], $categories)
            ? $data['base_category']
            : null;

        $term = Semester::with('academicYear')->findOrFail($data['semester_id']);
        $pupils = BulkBillingService::pupils($data['target'], (array) ($data['class_group_ids'] ?? []), array_map('intval', (array) ($data['student_ids'] ?? [])));

        return [$term, [
            'category' => $category,
            'mode' => $data['mode'],
            'amount' => (float) $data['amount'],
            'is_discount' => $isDiscount,
            'base_category' => $baseCategory,
            'existing' => $data['existing'],
        ], $pupils];
    }

    private function terms()
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }
}

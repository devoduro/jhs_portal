<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\Student;
use App\Services\FeeLedgerService;
use App\Support\FormLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Term fee schedule: one grid per term with a row per fee item (tuition, feeding, PTA
 * levy...) and a column per class level, plus billing that term to pupils.
 */
class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $terms = $this->terms();
        $term = $this->selectedTerm($request, $terms);

        $levels = FormLabel::options();
        $categories = FeeCategory::options();

        // amounts[category][level] => amount
        $amounts = [];
        if ($term) {
            foreach (FeeStructure::where('semester_id', $term->id)->get() as $structure) {
                $amounts[$structure->category][$structure->level] = (float) $structure->amount;
            }
        }

        $totals = [];
        foreach ($levels as $level => $label) {
            $totals[$level] = collect($amounts)->sum(fn ($row) => $row[$level] ?? 0);
        }

        $billedCount = $term
            ? \App\Models\StudentFeeCharge::where('semester_id', $term->id)->distinct('student_id')->count('student_id')
            : 0;

        // Active pupils per level, for the expected-income row.
        $pupils = Student::where('status', 'active')->selectRaw('level, COUNT(*) as n')->groupBy('level')->pluck('n', 'level')->map(fn ($n) => (int) $n)->all();
        $activePupils = array_sum($pupils);

        $stats = [
            'items' => count(array_filter($amounts, fn ($row) => array_sum($row) > 0)),
            'classes' => count(array_filter($totals, fn ($t) => $t > 0)),
            'min' => collect($totals)->filter(fn ($t) => $t > 0)->min() ?? 0,
            'max' => collect($totals)->max() ?? 0,
            'expected' => collect($totals)->map(fn ($t, $level) => $t * ($pupils[$level] ?? 0))->sum(),
        ];

        return view('fees.structures.index', compact('terms', 'term', 'levels', 'categories', 'amounts', 'totals', 'billedCount', 'pupils', 'activePupils', 'stats'));
    }

    /**
     * Save the whole grid for a term. Blank or zero cells remove that fee item for that class.
     */
    public function save(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'amounts' => 'nullable|array',
            'amounts.*' => 'array',
            'amounts.*.*' => 'nullable|numeric|min:0',
        ]);

        $term = Semester::findOrFail($request->semester_id);
        $categories = array_keys(FeeCategory::options());
        $levels = array_keys(FormLabel::OPTIONS);

        DB::transaction(function () use ($request, $term, $categories, $levels) {
            foreach ((array) $request->input('amounts', []) as $category => $row) {
                if (!in_array($category, $categories, true)) {
                    continue;
                }

                foreach ($row as $level => $amount) {
                    $level = (int) $level;
                    if (!in_array($level, $levels, true)) {
                        continue;
                    }

                    $match = [
                        'semester_id' => $term->id,
                        'category' => $category,
                        'level' => $level,
                    ];

                    if ($amount === null || $amount === '' || (float) $amount <= 0) {
                        FeeStructure::where($match)->delete();
                        continue;
                    }

                    FeeStructure::updateOrCreate($match, [
                        'academic_year_id' => $term->academic_year_id,
                        'amount' => $amount,
                    ]);
                }
            }
        });

        return redirect()->route('fee-structures.index', ['semester_id' => $term->id])
            ->with('success', 'Fee schedule for ' . $term->full_name . ' saved.');
    }

    /**
     * Copy another term's fee schedule into this one (existing amounts are overwritten).
     */
    public function copy(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'from_semester_id' => 'required|exists:semesters,id|different:semester_id',
        ]);

        $term = Semester::findOrFail($request->semester_id);
        $source = FeeStructure::where('semester_id', $request->from_semester_id)->get();

        if ($source->isEmpty()) {
            return back()->with('error', 'The term you chose to copy from has no fees set.');
        }

        DB::transaction(function () use ($term, $source) {
            foreach ($source as $structure) {
                FeeStructure::updateOrCreate(
                    ['semester_id' => $term->id, 'category' => $structure->category, 'level' => $structure->level],
                    ['academic_year_id' => $term->academic_year_id, 'amount' => $structure->amount]
                );
            }
        });

        return redirect()->route('fee-structures.index', ['semester_id' => $term->id])
            ->with('success', $source->count() . ' fee item(s) copied. Review the amounts and click Save.');
    }

    /**
     * Pick a term (and optionally classes / fee items) to bill.
     */
    public function billingForm(Request $request)
    {
        $terms = $this->terms();
        $term = $this->selectedTerm($request, $terms);
        $classGroups = ClassGroup::withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level')->orderBy('name')->get();

        $categoriesInTerm = $term
            ? FeeStructure::where('semester_id', $term->id)->distinct()->pluck('category')->all()
            : [];
        $categories = array_intersect_key(FeeCategory::options(), array_flip($categoriesInTerm));

        $plan = $term ? FeeLedgerService::termPlan($term) : null;

        return view('fees.structures.billing', compact('terms', 'term', 'classGroups', 'categories', 'plan'));
    }

    public function bill(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_group_ids' => 'nullable|array',
            'class_group_ids.*' => 'exists:class_groups,id',
            'student_id' => 'nullable|exists:students,id',
            'categories' => 'nullable|array',
            'refresh' => 'nullable|boolean',
        ]);

        $term = Semester::findOrFail($request->semester_id);

        $students = Student::where('status', 'active')
            ->whereNotNull('level')
            ->when($request->filled('class_group_ids'), fn ($q) => $q->whereIn('class_group_id', $request->class_group_ids))
            ->when($request->filled('student_id'), fn ($q) => $q->where('id', $request->student_id))
            ->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'No active pupils found in the selected classes.');
        }

        $stats = FeeLedgerService::billTerm($term, $students, (array) $request->input('categories', []), $request->boolean('refresh'));

        if ($stats['students'] === 0) {
            return back()->with('error', 'Nothing was billed - no fees are set for these pupils\' classes in ' . $term->full_name . '. Set the fee schedule first.');
        }

        $message = "Billed {$stats['students']} pupil(s) for {$term->full_name}: {$stats['billed']} new bill item(s)";
        if ($stats['updated']) {
            $message .= ", {$stats['updated']} updated";
        }
        if ($stats['skipped']) {
            $message .= ", {$stats['skipped']} already billed (skipped)";
        }

        if ($request->filled('student_id')) {
            return redirect()->route('fees.show', ['student' => $request->student_id, 'semester_id' => $term->id])->with('success', $message . '.');
        }

        return redirect()->route('fees.index', ['semester_id' => $term->id])->with('success', $message . '.');
    }

    private function terms()
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }

    private function selectedTerm(Request $request, $terms): ?Semester
    {
        return $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());
    }
}

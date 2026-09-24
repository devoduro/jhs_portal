<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TermReport;
use App\Support\FormLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * End-of-year promotion, one class at a time: every pupil in the class moves to a class at
 * the next level (e.g. Basic 4A -> Basic 5A), except pupils marked to repeat. Pupils in the
 * school's final class (normally JHS 3) are marked as completed instead.
 *
 * The promote/repeat decision is pre-filled from the "Promoted to" entry the class teacher
 * made on the third-term report, where there is one.
 *
 * Only students.level / class_group_id / status change - scores and fee records are keyed by
 * term, so pupils' history is untouched.
 */
class PromotionController extends Controller
{
    public function index()
    {
        $classGroups = ClassGroup::with('classTeacher')
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level')->orderBy('name')->get();

        $classGroups->each(function (ClassGroup $class) {
            $class->next_level = FormLabel::next($class->level);
            $class->suggested_target = $class->next_level ? $this->matchClassGroup($class) : null;
        });

        return view('promotions.index', compact('classGroups'));
    }

    public function preview(Request $request)
    {
        $request->validate(['class_group_id' => 'required|exists:class_groups,id']);

        $classGroup = ClassGroup::findOrFail($request->class_group_id);
        $nextLevel = FormLabel::next($classGroup->level);
        $isFinal = $nextLevel === null;

        $students = Student::where('class_group_id', $classGroup->id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            return redirect()->route('promotions.index')->with('error', "There are no pupils in {$classGroup->name}.");
        }

        // Decisions from the last term of the current academic year's reports, if entered.
        $finalTerm = Semester::whereHas('academicYear', fn ($q) => $q->where('is_current', true))
            ->orderByDesc('semester_number')->first();
        $decisions = $finalTerm
            ? TermReport::where('semester_id', $finalTerm->id)->whereIn('student_id', $students->pluck('id'))->pluck('promoted_to', 'student_id')
            : collect();

        $targetClasses = $isFinal ? collect() : ClassGroup::where('level', $nextLevel)->orderBy('name')->get();
        $suggested = $isFinal ? null : $this->matchClassGroup($classGroup);

        return view('promotions.preview', compact('classGroup', 'students', 'nextLevel', 'isFinal', 'targetClasses', 'suggested', 'decisions', 'finalTerm'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_group_id' => 'required|exists:class_groups,id',
            'target_class_group_id' => 'nullable|exists:class_groups,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'graduation_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $classGroup = ClassGroup::findOrFail($request->class_group_id);
        $nextLevel = FormLabel::next($classGroup->level);
        $moving = Student::where('class_group_id', $classGroup->id)
            ->where('status', 'active')
            ->whereIn('id', (array) $request->input('student_ids', []))
            ->pluck('id');

        if ($moving->isEmpty()) {
            return redirect()->route('promotions.index')->with('error', 'No pupils were ticked, so nobody was moved.');
        }

        if ($nextLevel === null) {
            $year = (int) ($request->input('graduation_year') ?: now()->year);
            Student::whereIn('id', $moving)->update(['class_group_id' => null, 'status' => 'graduated', 'graduation_year' => $year]);

            return redirect()->route('promotions.index')
                ->with('success', "{$moving->count()} pupil(s) marked as having completed {$classGroup->name} ({$year}).");
        }

        $target = $request->filled('target_class_group_id') ? ClassGroup::find($request->target_class_group_id) : null;

        if ($target && (int) $target->level !== $nextLevel) {
            return back()->with('error', 'The target class must be a ' . FormLabel::label($nextLevel) . ' class.');
        }

        DB::transaction(function () use ($moving, $nextLevel, $target) {
            Student::whereIn('id', $moving)->update(['level' => $nextLevel, 'class_group_id' => $target?->id]);
        });

        $repeating = Student::where('class_group_id', $classGroup->id)->where('status', 'active')->count();

        return redirect()->route('promotions.index')->with('success',
            "{$moving->count()} pupil(s) promoted from {$classGroup->name} to " . ($target?->name ?? FormLabel::label($nextLevel) . ' (not yet placed in a class)') . '.'
            . ($repeating ? " {$repeating} pupil(s) remain in {$classGroup->name}." : ''));
    }

    /**
     * The class at the next level with the same stream name, e.g. "Basic 4A" -> "Basic 5A",
     * "JHS 1 Gold" -> "JHS 2 Gold". Falls back to the only class at that level, if there is one.
     */
    private function matchClassGroup(ClassGroup $classGroup): ?ClassGroup
    {
        $nextLevel = FormLabel::next($classGroup->level);
        $candidates = ClassGroup::where('level', $nextLevel)->get();

        $currentLabel = FormLabel::label($classGroup->level);
        $expected = str_ireplace($currentLabel, FormLabel::label($nextLevel), $classGroup->name);

        return $candidates->first(fn ($c) => strcasecmp($c->name, $expected) === 0)
            ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }
}

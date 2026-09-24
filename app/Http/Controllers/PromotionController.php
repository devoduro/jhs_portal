<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Programme;
use App\Models\Student;
use App\Support\FormLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    /**
     * Show the programme + level picker. When a programme is selected, list its
     * levels that currently have active students, with counts, as promotion candidates.
     */
    public function index(Request $request)
    {
        $programmes = Programme::orderBy('name')->get();

        $programme = $request->filled('programme_id')
            ? Programme::find($request->programme_id)
            : null;

        $levels = [];

        if ($programme) {
            $terminalLevel = $programme->terminalLevel();

            for ($level = 100; $level <= $terminalLevel; $level += 100) {
                $count = Student::where('programme_id', $programme->id)
                    ->where('level', $level)
                    ->where('status', 'active')
                    ->count();

                if ($count > 0) {
                    $levels[] = [
                        'level' => $level,
                        'count' => $count,
                        'is_terminal' => $level === $terminalLevel,
                    ];
                }
            }
        }

        return view('promotions.index', compact('programmes', 'programme', 'levels'));
    }

    /**
     * Preview exactly which students will be affected before committing anything, including
     * which class each one will land in at the new level.
     */
    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'programme_id' => 'required|exists:programmes,id',
            'level' => 'required|integer|min:100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('promotions.index')
                ->withErrors($validator)
                ->withInput();
        }

        $programme = Programme::findOrFail($request->programme_id);
        $level = (int) $request->level;
        $isTerminal = $level >= $programme->terminalLevel();
        $targetLevel = $isTerminal ? null : $level + 100;

        $students = Student::with('classGroup')
            ->where('programme_id', $programme->id)
            ->where('level', $level)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            return redirect()->route('promotions.index')
                ->with('error', 'No active students found at that level for this programme.');
        }

        if (!$isTerminal) {
            $targetClassGroups = ClassGroup::where('programme_id', $programme->id)
                ->where('level', $targetLevel)
                ->get();

            $students->each(function (Student $student) use ($level, $targetLevel, $targetClassGroups) {
                $student->predicted_class_group = $student->classGroup
                    ? $this->matchClassGroup($student->classGroup->name, $level, $targetLevel, $targetClassGroups)
                    : null;
            });
        }

        $defaultGraduationYear = now()->year;

        return view('promotions.preview', compact('programme', 'level', 'targetLevel', 'isTerminal', 'students', 'defaultGraduationYear'));
    }

    /**
     * Commit the promotion: bump level (or mark graduated at the terminal level).
     *
     * When promoting, each student is carried into the class at the new level whose name
     * matches their current class with the form digit swapped (e.g. "1S12" -> "2S12", "Science
     * 1A" -> "Science 2A") if such a class exists; otherwise they're left unassigned for manual
     * reassignment via Classes > Assign Students.
     *
     * Only ever touches students.level/class_group_id/status/graduation_year - results,
     * registrations, continuous assessment and arrears are keyed by academic_year_id (not level
     * or class group) and are never read or written here, so continuing students' historical
     * records are left untouched.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'programme_id' => 'required|exists:programmes,id',
            'level' => 'required|integer|min:100',
            'graduation_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('promotions.index')
                ->withErrors($validator)
                ->withInput();
        }

        $programme = Programme::findOrFail($request->programme_id);
        $level = (int) $request->level;
        $isTerminal = $level >= $programme->terminalLevel();
        $graduationYear = (int) ($request->input('graduation_year') ?: now()->year);

        $count = 0;
        $carried = 0;

        DB::transaction(function () use ($programme, $level, $isTerminal, $graduationYear, &$count, &$carried) {
            $scope = Student::where('programme_id', $programme->id)
                ->where('level', $level)
                ->where('status', 'active');

            $students = $scope->get();
            $count = $students->count();

            if ($count === 0) {
                return;
            }

            if ($isTerminal) {
                $scope->update(['class_group_id' => null, 'status' => 'graduated', 'graduation_year' => $graduationYear]);

                return;
            }

            $targetLevel = $level + 100;
            $targetClassGroups = ClassGroup::where('programme_id', $programme->id)
                ->where('level', $targetLevel)
                ->get();

            foreach ($students->groupBy('class_group_id') as $classGroupId => $studentsInClass) {
                $currentName = $classGroupId ? optional(ClassGroup::find($classGroupId))->name : null;

                $matched = $currentName
                    ? $this->matchClassGroup($currentName, $level, $targetLevel, $targetClassGroups)
                    : null;

                if ($matched) {
                    $carried += $studentsInClass->count();
                }

                Student::whereIn('id', $studentsInClass->pluck('id'))
                    ->update(['level' => $targetLevel, 'class_group_id' => $matched?->id]);
            }
        });

        if ($count === 0) {
            return redirect()->route('promotions.index')
                ->with('error', 'No active students found at that level for this programme — they may have already been promoted.');
        }

        if ($isTerminal) {
            $message = "{$count} student(s) marked as graduated from " . FormLabel::label($level) . '.';
        } else {
            $unmatched = $count - $carried;
            $message = "{$count} student(s) promoted from " . FormLabel::label($level) . ' to ' . FormLabel::label($level + 100) . '. '
                . ($carried > 0 ? "{$carried} carried into their matching class automatically. " : '')
                . ($unmatched > 0 ? "{$unmatched} have no matching class at " . FormLabel::label($level + 100) . ' yet — assign them via Classes > Assign Students.' : '');
        }

        return redirect()->route('promotions.index')->with('success', trim($message));
    }

    /**
     * Find the class at the target level whose name matches the given current-level class name
     * with the form digit swapped - e.g. "1S12" -> "2S12", "Science 1A" -> "Science 2A". The
     * digit must appear as a standalone token (not part of a larger number) so names like
     * "Room 100" aren't mangled. Returns null if no such class exists yet.
     */
    private function matchClassGroup(string $currentName, int $currentLevel, int $targetLevel, \Illuminate\Support\Collection $targetClassGroups): ?ClassGroup
    {
        $currentDigit = intdiv($currentLevel, 100);
        $targetDigit = intdiv($targetLevel, 100);

        $expectedName = preg_replace(
            '/(?<!\d)' . preg_quote((string) $currentDigit, '/') . '(?!\d)/',
            (string) $targetDigit,
            $currentName,
            1
        );

        if ($expectedName === $currentName) {
            return null;
        }

        return $targetClassGroups->first(
            fn (ClassGroup $candidate) => strcasecmp($candidate->name, $expectedName) === 0
        );
    }
}

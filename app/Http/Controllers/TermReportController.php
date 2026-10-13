<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\TermReport;
use App\Services\TerminalReportService;
use App\Support\FormLabel;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class teacher's page for the non-academic part of the terminal report: attendance,
 * conduct, attitude, interest, class teacher's remark, head teacher's remark and (in the
 * last term of the year) the class each pupil is promoted to.
 *
 * Teachers see classes they are class teacher of and classes at levels they teach, and cannot write the head teacher's
 * remark; the head teacher / exams officer / admin see every class.
 */
class TermReportController extends Controller
{
    use ScopesToTeacher;

    public function index(Request $request)
    {
        $terms = Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
        $term = $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $classGroups = $this->allowedClasses();
        $classGroup = $request->filled('class_group_id')
            ? $classGroups->firstWhere('id', (int) $request->class_group_id)
            : ($classGroups->count() === 1 ? $classGroups->first() : null);

        $data = null;
        if ($term && $classGroup) {
            $data = TerminalReportService::forClass($classGroup, $term);
        }

        $canWriteHeadRemark = $this->canWriteHeadRemark();
        $isFinalTerm = $term?->isFinalTerm() ?? false;

        return view('term-reports.index', compact('terms', 'term', 'classGroups', 'classGroup', 'data', 'canWriteHeadRemark', 'isFinalTerm'));
    }

    public function save(Request $request, ClassGroup $classGroup)
    {
        abort_unless($this->allowedClasses()->contains('id', $classGroup->id), 403, 'You are not assigned to this class.');

        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'attendance_days' => 'nullable|integer|min:0|max:200',
            'reports' => 'nullable|array',
            'reports.*.days_present' => 'nullable|integer|min:0|max:200',
            'reports.*.conduct' => 'nullable|string|max:100',
            'reports.*.attitude' => 'nullable|string|max:100',
            'reports.*.interest' => 'nullable|string|max:100',
            'reports.*.class_teacher_remark' => 'nullable|string|max:500',
            'reports.*.head_teacher_remark' => 'nullable|string|max:500',
            'reports.*.promoted_to' => 'nullable|integer|' . FormLabel::rule() . ',0',
        ]);

        $term = Semester::findOrFail($request->semester_id);
        $allowedIds = TerminalReportService::classRoll($classGroup, $term)->pluck('id')->all();
        $canWriteHeadRemark = $this->canWriteHeadRemark();

        // Total school days in the term is shared by every class, so it lives on the term.
        if ($request->filled('attendance_days')) {
            $term->update(['attendance_days' => $request->attendance_days]);
        }

        DB::transaction(function () use ($request, $term, $classGroup, $allowedIds, $canWriteHeadRemark) {
            foreach ((array) $request->input('reports', []) as $studentId => $row) {
                if (!in_array((int) $studentId, $allowedIds, true)) {
                    continue;
                }

                $fields = [
                    'class_group_id' => $classGroup->id,
                    'days_present' => $row['days_present'] ?? null,
                    'conduct' => $row['conduct'] ?? null,
                    'attitude' => $row['attitude'] ?? null,
                    'interest' => $row['interest'] ?? null,
                    'class_teacher_remark' => $row['class_teacher_remark'] ?? null,
                ];

                if ($canWriteHeadRemark) {
                    $fields['head_teacher_remark'] = $row['head_teacher_remark'] ?? null;
                }

                if (array_key_exists('promoted_to', $row)) {
                    // 0 = repeat the class / not promoted.
                    $fields['promoted_to'] = $row['promoted_to'] === '' ? null : (int) $row['promoted_to'];
                }

                TermReport::updateOrCreate(
                    ['student_id' => (int) $studentId, 'semester_id' => $term->id],
                    $fields
                );
            }
        });

        return redirect()->route('term-reports.index', ['class_group_id' => $classGroup->id, 'semester_id' => $term->id])
            ->with('success', 'Attendance and remarks saved for ' . $classGroup->name . '.');
    }

    private function allowedClasses()
    {
        $classes = ClassGroup::with('classTeacher')->orderBy('level')->orderBy('name')->get();

        if (!$this->isScopedTeacher()) {
            return $classes;
        }

        // Their own class, plus any class at a level where they teach a subject (the same classes
        // they can enter scores for), so a teacher can complete reports for classes they teach.
        $teacherId = $this->authTeacherId() ?? 0;
        $subjectLevels = \App\Models\Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))->pluck('level')->unique();

        return $classes->filter(fn ($c) => $c->class_teacher_id === $teacherId || $subjectLevels->contains($c->level))->values();
    }

    private function canWriteHeadRemark(): bool
    {
        $user = auth()->user();

        return !$this->isScopedTeacher() && ($user->hasRole('Super Admin') || $user->hasRole('Head Teacher') || $user->can('manage-results'));
    }
}

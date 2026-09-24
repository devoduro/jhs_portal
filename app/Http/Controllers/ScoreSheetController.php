<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\TerminalReportService;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Score entry for terminal reports: pick a term, class and subject, then type each pupil's
 * class score and exam score. Totals, grades, remarks and positions are worked out
 * automatically. Also the class broadsheet (every subject for every pupil, with positions).
 *
 * A teacher (without Exams Officer / Super Admin) only sees:
 *  - every subject of a class they are the class teacher of (in primary the class teacher
 *    usually teaches all subjects), and
 *  - subjects they are assigned to teach, in any class at that subject's level.
 */
class ScoreSheetController extends Controller
{
    use ScopesToTeacher;

    public function index(Request $request)
    {
        $terms = $this->terms();
        $term = $this->selectedTerm($request, $terms);
        $classGroups = $this->allowedClasses();
        $classGroup = $request->filled('class_group_id') ? $classGroups->firstWhere('id', (int) $request->class_group_id) : null;

        $subjects = $classGroup ? $this->allowedSubjects($classGroup) : collect();

        // Completion status per subject: how many pupils have a score.
        $progress = [];
        if ($classGroup && $term) {
            $roll = TerminalReportService::classRoll($classGroup, $term);
            $counts = Result::where('semester_id', $term->id)
                ->whereIn('student_id', $roll->pluck('id'))
                ->whereIn('subject_id', $subjects->pluck('id'))
                ->select('subject_id', DB::raw('COUNT(*) as n'))
                ->groupBy('subject_id')
                ->pluck('n', 'subject_id');

            foreach ($subjects as $subject) {
                $progress[$subject->id] = ['entered' => (int) ($counts[$subject->id] ?? 0), 'roll' => $roll->count()];
            }
        }

        return view('scores.index', compact('terms', 'term', 'classGroups', 'classGroup', 'subjects', 'progress'));
    }

    public function sheet(Request $request, ClassGroup $classGroup, Subject $subject)
    {
        $this->authorizeSheet($classGroup, $subject);

        $term = Semester::with('academicYear')->findOrFail($request->integer('semester_id') ?: optional(Semester::where('is_current', true)->first())->id);
        $students = TerminalReportService::classRoll($classGroup, $term);

        $results = Result::where('semester_id', $term->id)
            ->where('subject_id', $subject->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $positions = TerminalReportService::rank($results->mapWithKeys(fn ($r) => [$r->student_id => (float) $r->score])->all());

        $classMax = TerminalReportService::classScoreMax();
        $examMax = TerminalReportService::examScoreMax();

        return view('scores.sheet', compact('classGroup', 'subject', 'term', 'students', 'results', 'positions', 'classMax', 'examMax'));
    }

    public function save(Request $request, ClassGroup $classGroup, Subject $subject)
    {
        $this->authorizeSheet($classGroup, $subject);

        $classMax = TerminalReportService::classScoreMax();
        $examMax = TerminalReportService::examScoreMax();

        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'scores' => 'required|array',
            'scores.*.class_score' => "nullable|numeric|min:0|max:{$classMax}",
            'scores.*.exam_score' => "nullable|numeric|min:0|max:{$examMax}",
        ], [
            'scores.*.class_score.max' => "Class score cannot be more than {$classMax}.",
            'scores.*.exam_score.max' => "Exam score cannot be more than {$examMax}.",
        ]);

        $term = Semester::findOrFail($request->semester_id);

        // Only pupils on this class's roll can be scored from this sheet.
        $allowedIds = TerminalReportService::classRoll($classGroup, $term)->pluck('id')->all();
        $scores = array_intersect_key((array) $request->input('scores'), array_flip($allowedIds));

        $saved = TerminalReportService::saveScores($classGroup, $subject, $term, $scores);

        return redirect()->route('scores.sheet', ['classGroup' => $classGroup, 'subject' => $subject, 'semester_id' => $term->id])
            ->with('success', "{$subject->title} scores saved for {$saved} pupil(s).");
    }

    /**
     * Class broadsheet: every pupil's total in every subject with overall position.
     */
    public function broadsheet(Request $request, ClassGroup $classGroup)
    {
        abort_unless($this->allowedClasses()->contains('id', $classGroup->id), 403);

        $term = Semester::with('academicYear')->findOrFail($request->integer('semester_id') ?: optional(Semester::where('is_current', true)->first())->id);
        $data = TerminalReportService::forClass($classGroup, $term);
        $data['pupils'] = $data['pupils']->sortBy(fn ($p) => $p['position'] ?? PHP_INT_MAX)->values();
        $data['settings'] = DB::table('settings')->where('category', 'institution')->pluck('value', 'key')->toArray();
        $data['print'] = $request->boolean('print');

        return view('scores.broadsheet', $data);
    }

    private function allowedClasses(): Collection
    {
        $classes = ClassGroup::with('classTeacher')->orderBy('level')->orderBy('name')->get();

        if (!$this->isScopedTeacher()) {
            return $classes;
        }

        $teacherId = $this->authTeacherId() ?? 0;
        $subjectLevels = Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))->pluck('level')->unique();

        return $classes->filter(fn ($c) => $c->class_teacher_id === $teacherId || $subjectLevels->contains($c->level))->values();
    }

    private function allowedSubjects(ClassGroup $classGroup): Collection
    {
        $subjects = TerminalReportService::subjectsFor($classGroup->level);

        if (!$this->isScopedTeacher() || $classGroup->class_teacher_id === $this->authTeacherId()) {
            return $subjects;
        }

        return $subjects->whereIn('id', $this->teacherSubjectIds())->values();
    }

    private function authorizeSheet(ClassGroup $classGroup, Subject $subject): void
    {
        abort_unless((int) $subject->level === (int) $classGroup->level, 404, 'That subject is not taught in this class.');
        abort_unless($this->allowedSubjects($classGroup)->contains('id', $subject->id), 403, 'You are not assigned to this class or subject.');
    }

    private function terms(): Collection
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }

    private function selectedTerm(Request $request, Collection $terms): ?Semester
    {
        return $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\TerminalReportService;
use App\Traits\ScopesToTeacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * "All Results": a searchable list of every score recorded, and one score's details.
 *
 * Scores are only ever entered or changed on the class score sheet (ScoreSheetController),
 * which keeps the class score / exam score split, grading and the class snapshot consistent.
 * The old one-at-a-time add/edit and spreadsheet upload actions now take staff there.
 *
 * Teachers only see scores for subjects they teach or classes they are class teacher of.
 */
class ResultController extends Controller
{
    use ScopesToTeacher;

    public function index(Request $request)
    {
        $query = $this->visibleResults()->with(['student', 'subject', 'semester.academicYear', 'classGroup']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('index_number', 'like', "%{$search}%"));
        }

        foreach (['semester_id', 'class_group_id', 'subject_id', 'student_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('min_score')) {
            $query->where('score', '>=', $request->min_score);
        }
        if ($request->filled('max_score')) {
            $query->where('score', '<=', $request->max_score);
        }

        $results = $query->orderByDesc('semester_id')->orderBy('class_group_id')->orderByDesc('score')
            ->paginate(25)->withQueryString();

        $terms = Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])->values();
        $classGroups = $this->visibleClasses();
        $subjects = Subject::whereIn('id', $this->visibleResults()->select('subject_id')->distinct())
            ->orderBy('level')->orderBy('title')->get();

        return view('results.index', compact('results', 'terms', 'classGroups', 'subjects'));
    }

    public function show(Result $result)
    {
        $this->authorizeResult($result);
        $result->load(['student.classGroup', 'subject', 'semester.academicYear', 'classGroup', 'academicYear']);

        $classGroup = $result->classGroup ?? TerminalReportService::classFor($result->student, $result->semester);

        // Position in this subject among the class, for context.
        $position = null;
        $classSize = null;
        if ($classGroup) {
            $scores = Result::where('semester_id', $result->semester_id)
                ->where('subject_id', $result->subject_id)
                ->where('class_group_id', $classGroup->id)
                ->pluck('score', 'student_id')
                ->map(fn ($s) => (float) $s)
                ->all();
            $positions = TerminalReportService::rank($scores);
            $position = $positions[$result->student_id] ?? null;
            $classSize = count($scores);
        }

        return view('results.show', compact('result', 'classGroup', 'position', 'classSize'));
    }

    /**
     * Editing a single score happens on that class's score sheet for the subject and term.
     */
    public function edit(string $id)
    {
        $result = Result::with('student')->findOrFail($id);
        $this->authorizeResult($result);

        return $this->toScoreSheet($result->classGroup ?? $result->student?->classGroup, $result->subject_id, $result->semester_id)
            ?? redirect()->route('results.show', $result)->with('error', 'This pupil is not in a class, so the score cannot be edited on a score sheet. Place the pupil in a class first.');
    }

    public function update(Request $request, string $id)
    {
        return $this->edit($id);
    }

    /**
     * "Add result" links (with optional student/subject/term) open the matching score sheet.
     */
    public function create(Request $request)
    {
        $student = $request->filled('student_id') ? \App\Models\Student::with('classGroup')->find($request->student_id) : null;

        return ($student ? $this->toScoreSheet($student->classGroup, $request->input('subject_id'), $request->input('semester_id')) : null)
            ?? redirect()->route('scores.index')->with('warning', 'Scores are entered for a whole class at once. Choose the class and subject below.');
    }

    public function store(Request $request)
    {
        return $this->create($request);
    }

    public function destroy(string $id)
    {
        abort_unless(auth()->user()->can('manage-results'), 403);

        $result = Result::with(['student', 'subject'])->findOrFail($id);
        $label = ($result->student->full_name ?? 'Pupil') . ' — ' . ($result->subject->title ?? 'subject');
        $result->delete();

        return redirect()->route('results.index')->with('success', "Score deleted: {$label}.");
    }

    // Older entry points kept so bookmarks and links keep working.
    public function search(Request $request)
    {
        return redirect()->route('results.index', $request->only('search'));
    }

    public function filterByAcademicYear(Request $request)
    {
        return redirect()->route('results.index');
    }

    public function filterBySemester(Request $request)
    {
        return redirect()->route('results.index', array_filter(['semester_id' => $request->input('semester_id')]));
    }

    public function filterByStudent(Request $request)
    {
        return redirect()->route('results.index', array_filter(['student_id' => $request->input('student_id')]));
    }

    public function filterBySubject(Request $request)
    {
        return redirect()->route('results.index', array_filter(['subject_id' => $request->input('subject_id')]));
    }

    public function bulkCreate()
    {
        return redirect()->route('scores.index')->with('warning', 'Scores are now entered on the class score sheet: choose the class, then the subject.');
    }

    public function bulkStore(Request $request)
    {
        return $this->bulkCreate();
    }

    public function downloadTemplate()
    {
        return $this->bulkCreate();
    }

    private function toScoreSheet(?ClassGroup $classGroup, $subjectId, $semesterId)
    {
        if (!$classGroup) {
            return null;
        }

        $subject = $subjectId ? Subject::find($subjectId) : null;
        $termId = $semesterId ?: optional(Semester::where('is_current', true)->first())->id;

        if ($subject && (int) $subject->level === (int) $classGroup->level) {
            return redirect()->route('scores.sheet', ['classGroup' => $classGroup, 'subject' => $subject, 'semester_id' => $termId]);
        }

        return redirect()->route('scores.index', ['class_group_id' => $classGroup->id, 'semester_id' => $termId]);
    }

    /**
     * Results the signed-in user may see.
     */
    private function visibleResults(): Builder
    {
        $query = Result::query();

        if ($this->isScopedTeacher()) {
            $teacherId = $this->authTeacherId() ?? 0;
            $classIds = ClassGroup::where('class_teacher_id', $teacherId)->pluck('id');

            $query->where(fn ($q) => $q->whereIn('subject_id', $this->teacherSubjectIds())->orWhereIn('class_group_id', $classIds));
        }

        return $query;
    }

    private function visibleClasses()
    {
        $classes = ClassGroup::orderBy('level')->orderBy('name');

        if ($this->isScopedTeacher()) {
            $classes->whereIn('id', $this->visibleResults()->select('class_group_id')->distinct());
        }

        return $classes->get();
    }

    private function authorizeResult(Result $result): void
    {
        abort_unless($this->visibleResults()->whereKey($result->id)->exists(), 403, 'You can only view scores for your own classes and subjects.');
    }
}

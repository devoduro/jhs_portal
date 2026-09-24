<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\GradeScheme;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Student;
use App\Services\TerminalReportService;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Terminal reports, viewable:
 *  - show(): publicly via a signed link texted to a parent (see GradeNotificationService);
 *  - adminShow()/bulkForm()/bulkPrint(): by staff, one pupil or a whole class at once;
 *  - studentShow(): by the logged-in pupil for any term they have scores in.
 */
class ReportCardController extends Controller
{
    use ScopesToTeacher;

    public function show(Request $request, Student $student)
    {
        $term = $this->termFor($student, $request->integer('semester_id') ?: null);
        abort_unless($term, 404, 'No terminal report is available yet.');

        $card = $this->cardFor($student, $term);
        abort_unless($card, 404, 'No terminal report is available yet.');

        return view('report-card.public', ['cards' => collect([$card]), 'publicLink' => true, 'student' => $student]);
    }

    public function adminShow(Request $request, Student $student)
    {
        $term = $this->termFor($student, $request->integer('semester_id') ?: null);

        if (!$term) {
            return back()->with('error', 'No terms have been created yet.');
        }

        $card = $this->cardFor($student, $term);

        if (!$card) {
            return back()->with('error', $student->full_name . ' is not in a class, so no terminal report can be produced.');
        }

        return view('report-card.public', [
            'cards' => collect([$card]),
            'publicLink' => false,
            'student' => $student,
            'terms' => $this->terms(),
            'selectedTermId' => $term->id,
        ]);
    }

    /**
     * The logged-in pupil's own reports - only terms they have scores in.
     */
    public function studentShow(Request $request)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return redirect()->route('student.dashboard')->with('error', 'Student profile not found.');
        }

        $availableReports = $student->results()
            ->with('semester.academicYear')
            ->get()
            ->pluck('semester')
            ->unique('id')
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->map(fn (Semester $term) => [
                'semester_id' => $term->id,
                'label' => $term->full_name,
            ])
            ->values();

        $selectedSemesterId = $request->integer('semester_id') ?: null;

        if ($selectedSemesterId && !$availableReports->contains('semester_id', $selectedSemesterId)) {
            $selectedSemesterId = null;
        }

        $reportData = $selectedSemesterId ? $this->cardFor($student, Semester::find($selectedSemesterId)) : null;

        return view('student.report-card', compact('student', 'availableReports', 'selectedSemesterId', 'reportData'));
    }

    /**
     * Choose a term and class (and optionally particular pupils) to print.
     */
    public function bulkForm(Request $request)
    {
        $classGroups = $this->allowedClasses();
        $terms = $this->terms();

        $students = collect();
        $term = $request->filled('semester_id') ? $terms->firstWhere('id', (int) $request->semester_id) : ($terms->firstWhere('is_current', true) ?? $terms->first());

        if ($request->filled('class_group_id') && $term) {
            $classGroup = $classGroups->firstWhere('id', (int) $request->class_group_id);
            $students = $classGroup ? TerminalReportService::classRoll($classGroup, $term) : collect();
        }

        return view('report-card.bulk-form', [
            'classGroups' => $classGroups,
            'semesters' => $terms,
            'students' => $students,
            'selectedTerm' => $term,
        ]);
    }

    /**
     * A whole class's terminal reports (or selected pupils), one per A4 page.
     */
    public function bulkPrint(Request $request)
    {
        $request->validate([
            'class_group_id' => 'required|exists:class_groups,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'student_ids' => 'nullable|array',
        ]);

        $classGroup = ClassGroup::findOrFail($request->class_group_id);
        abort_unless($this->allowedClasses()->contains('id', $classGroup->id), 403);

        $term = Semester::with('academicYear')->find($request->integer('semester_id') ?: optional(Semester::where('is_current', true)->first())->id);

        if (!$term) {
            return back()->with('error', 'Choose a term.');
        }

        $onlyIds = $request->filled('student_ids') ? array_map('intval', (array) $request->student_ids) : null;
        $data = TerminalReportService::forClass($classGroup, $term, $onlyIds);

        if ($data['pupils']->isEmpty()) {
            return back()->with('error', 'No pupils to print reports for.');
        }

        $cards = $data['pupils']->map(fn ($pupil) => $this->decorate($pupil, $data));

        return view('report-card.bulk', compact('cards'));
    }

    /**
     * One pupil's card for a term, or null if the pupil has no class to rank them in.
     */
    protected function cardFor(Student $student, Semester $term): ?array
    {
        $classGroup = TerminalReportService::classFor($student, $term);

        if (!$classGroup) {
            return null;
        }

        $data = TerminalReportService::forClass($classGroup, $term, [$student->id]);
        $pupil = $data['pupils']->first();

        return $pupil ? $this->decorate($pupil, $data) : null;
    }

    /**
     * Add the class-wide details (term dates, roll, grading key, settings) and the pupil's
     * fee position to one pupil's report data.
     */
    protected function decorate(array $pupil, array $classData): array
    {
        static $shared = [];
        $key = $classData['classGroup']->id . '-' . $classData['term']->id;

        if (!isset($shared[$key])) {
            $scheme = GradeScheme::forLevel($classData['classGroup']->level);

            $shared[$key] = [
                'settings' => DB::table('settings')->whereIn('category', ['institution', 'system'])
                    ->orderByRaw("category = 'institution'")->pluck('value', 'key')->toArray(),
                'gradeBands' => $scheme ? $scheme->grades->sortByDesc('min_score')->values() : collect(),
                'classMax' => TerminalReportService::classScoreMax(),
                'examMax' => TerminalReportService::examScoreMax(),
                'showFees' => Setting::get('report_show_fees', '1') === '1',
                'headTitle' => Setting::get('head_title', 'Head Teacher'),
            ];
        }

        return $pupil + $shared[$key] + [
            'classGroup' => $classData['classGroup'],
            'term' => $classData['term'],
            'roll' => $classData['roll'],
            'classAverage' => $classData['classAverage'],
            'isJhs' => $classData['isJhs'],
            'isFinalTerm' => $classData['term']->isFinalTerm(),
            'fees' => TerminalReportService::feeSummary($pupil['student'], $classData['term']),
        ];
    }

    /**
     * The requested term, else the latest term the pupil has scores in, else the current term.
     */
    protected function termFor(Student $student, ?int $semesterId): ?Semester
    {
        if ($semesterId) {
            return Semester::with('academicYear')->find($semesterId);
        }

        $latest = $student->results()->with('semester.academicYear')->get()->pluck('semester')->filter()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->first();

        return $latest ?? Semester::with('academicYear')->where('is_current', true)->first();
    }

    private function terms()
    {
        return Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
    }

    private function allowedClasses()
    {
        $classes = ClassGroup::orderBy('level')->orderBy('name')->get();

        if (!$this->isScopedTeacher()) {
            return $classes;
        }

        $teacherId = $this->authTeacherId() ?? 0;
        $subjectLevels = \App\Models\Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))->pluck('level')->unique();

        return $classes->filter(fn ($c) => $c->class_teacher_id === $teacherId || $subjectLevels->contains($c->level))->values();
    }
}

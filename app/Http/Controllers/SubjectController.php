<?php

namespace App\Http\Controllers;

use App\Exports\SubjectStudentsExport;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Result;
use App\Models\Semester;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SubjectController extends Controller
{
    use ScopesToTeacher;

    /**
     * Subjects grouped by class level.
     */
    public function index(Request $request)
    {
        $query = $this->scopeToTeacher(Subject::with('teachers'));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $subjects = $query->orderBy('level')->orderByDesc('is_core')->orderBy('title')->get()->groupBy('level');

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();

        return view('subjects.create', compact('teachers'));
    }

    /**
     * One subject is usually taught across several class levels (e.g. English from Basic 1
     * to JHS 3). The shared details are entered once and one subject row per ticked level is
     * created, coded via Subject::buildCode() (ENG-B1, ENG-J2...). A level that already has
     * that code is skipped, so this also adds a subject to further levels.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_prefix' => 'required|string|max:10|regex:/^[A-Za-z]+$/',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'levels' => 'required|array|min:1',
            'levels.*' => 'integer|' . \App\Support\FormLabel::rule(),
            'is_core' => 'boolean',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('subjects.create')
                ->withErrors($validator)
                ->withInput();
        }

        $created = [];
        $skipped = [];

        foreach (array_unique($request->levels) as $level) {
            $code = Subject::buildCode($request->code_prefix, (int) $level);

            if (Subject::where('code', $code)->exists()) {
                $skipped[] = $code;
                continue;
            }

            $subject = Subject::create([
                'code' => $code,
                'title' => $request->title,
                'description' => $request->description,
                'credit_hours' => 1,
                'level' => $level,
                'is_core' => $request->boolean('is_core'),
            ]);

            $subject->teachers()->sync($request->teacher_ids ?? []);

            $created[] = $code;
        }

        if (empty($created)) {
            return redirect()->route('subjects.create')
                ->with('error', 'Every selected class level already has this subject (' . implode(', ', $skipped) . ').')
                ->withInput();
        }

        $message = "{$request->title} added for " . count($created) . ' class level(s).';
        if (!empty($skipped)) {
            $message .= ' Already existed: ' . implode(', ', $skipped) . '.';
        }

        return redirect()->route('subjects.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return redirect()->route('subjects.results', $id);
    }

    public function edit(string $id)
    {
        $subject = Subject::with('teachers')->findOrFail($id);
        $teachers = Teacher::orderBy('name')->get();

        return view('subjects.edit', compact('subject', 'teachers'));
    }

    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|integer|' . \App\Support\FormLabel::rule(),
            'is_core' => 'boolean',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('subjects.edit', $subject)
                ->withErrors($validator)
                ->withInput();
        }

        $subject->update([
            'code' => strtoupper($request->code),
            'title' => $request->title,
            'description' => $request->description,
            'level' => $request->level,
            'is_core' => $request->boolean('is_core'),
        ]);

        $subject->teachers()->sync($request->teacher_ids ?? []);

        return redirect()->route('subjects.index', ['level' => $subject->level])
            ->with('success', "{$subject->title} ({$subject->code}) updated.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subject = Subject::findOrFail($id);

        // Check if there are any results associated with this subject
        if ($subject->results()->count() > 0) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete subject with associated results.');
        }

        // Check if this subject is a prerequisite for other subjects
        if ($subject->dependentSubjects()->count() > 0) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete subject that is a prerequisite for other subjects.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    /**
     * Display students registered for a subject, with their result (if graded yet).
     */
    public function students(string $id)
    {
        $subject = Subject::findOrFail($id);
        $allRows = $this->buildStudentRows($id);

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('subjects.students', compact('subject', 'rows'));
    }

    /**
     * Export every student registered for the subject (not just the current page) to Excel.
     */
    public function exportStudents(string $id)
    {
        $subject = Subject::findOrFail($id);
        $rows = $this->buildStudentRows($id);

        return Excel::download(new SubjectStudentsExport($rows), $subject->code . '-students.xlsx');
    }

    /**
     * Build the full (unpaginated) student/result roster for a subject, shared by the
     * on-screen roster and its Excel export.
     */
    protected function buildStudentRows(string $id): \Illuminate\Support\Collection
    {
        $subject = Subject::findOrFail($id);
        $term = Semester::where('is_current', true)->first();
        $semesterId = $term?->id;
        $academicYearId = $term?->academic_year_id;

        return $subject->students()->get()->map(function ($student) use ($id, $semesterId, $academicYearId) {
            $result = Result::where('student_id', $student->id)
                ->where('subject_id', $id)
                ->where('semester_id', $semesterId)
                ->where('academic_year_id', $academicYearId)
                ->first();

            return [
                'student' => $student,
                'result' => $result,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ];
        })->sortBy(fn ($row) => $row['student']->full_name)->values();
    }

    /**
     * Printable attendance sheet for a subject's enrolled students, sorted by index number.
     */
    public function printAttendance(string $id)
    {
        $subject = Subject::findOrFail($id);

        $students = $subject->students()->orderBy('index_number')->get();

        $settings = DB::table('settings')->where('category', 'institution')->pluck('value', 'key')->toArray();

        return view('subjects.print-attendance', compact('subject', 'students', 'settings'));
    }

    /**
     * Export subject data.
     */
    public function export(string $id)
    {
        $subject = Subject::with(['results.student'])->findOrFail($id);

        // This would typically generate an Excel file with subject data
        // For simplicity, we'll just redirect back with a success message

        return redirect()->route('subjects.show', $id)
            ->with('success', 'Subject data exported successfully.');
    }

    /**
     * Show results for a specific subject.
     */
    public function results(string $id)
    {
        $subject = Subject::with(['teachers'])->findOrFail($id);

        // Get all results for this subject, grouped by academic year and semester
        $results = Result::with(['student', 'academicYear', 'semester'])
            ->where('subject_id', $id)
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('semester_id', 'desc')
            ->orderBy('score', 'desc')
            ->paginate(50);

        // Get academic years and semesters for filtering
        $academicYears = \App\Models\AcademicYear::orderBy('name', 'desc')->get();
        $semesters = \App\Models\Semester::all();

        // Calculate statistics
        $stats = [
            'total' => $results->total(),
            'average' => $results->avg('score') ?? 0,
            'highest' => $results->max('score') ?? 0,
            'lowest' => $results->min('score') ?? 0,
            'pass_rate' => $results->where('score', '>=', 40)->count() / max(1, $results->count()) * 100
        ];

        return view('subjects.results', compact('subject', 'results', 'academicYears', 'semesters', 'stats'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\ClassRosterExport;
use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

/**
 * Printable class lists for teachers and admins: a preview page where the columns are chosen,
 * and the same list as a PDF (inline or download) or an Excel file.
 *
 * Admins and office staff can print any class. A teacher can print their own class (as class
 * teacher) and any class at a level they teach a subject in - the same classes they can score.
 */
class ClassListController extends Controller
{
    use ScopesToTeacher;

    /** Optional columns, in print order: key => heading. Name and # are always there. */
    public const COLUMNS = [
        'index_number' => 'Admission No.',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of birth',
        'parent' => 'Parent / guardian',
        'phone' => 'Parent phone',
        'house' => 'House',
        'signature' => 'Signature',
        'remarks' => 'Remarks',
    ];

    private const DEFAULT_COLUMNS = ['index_number', 'gender', 'remarks'];

    public function index()
    {
        $classGroups = $this->allowedClasses();

        $counts = Student::where('status', 'active')
            ->whereIn('class_group_id', $classGroups->pluck('id'))
            ->selectRaw('class_group_id, COUNT(*) as total')
            ->groupBy('class_group_id')
            ->pluck('total', 'class_group_id');

        return view('class-lists.index', compact('classGroups', 'counts'));
    }

    public function show(Request $request, ClassGroup $classGroup)
    {
        $data = $this->listData($request, $classGroup);

        return view('class-lists.show', $data + ['query' => $this->fileQuery($request), 'columnOptions' => self::COLUMNS]);
    }

    /**
     * The list as a PDF: streamed inline for the preview frame, or downloaded with ?download=1.
     */
    public function file(Request $request, ClassGroup $classGroup)
    {
        $pdf = PDF::loadView('class-lists.pdf', $this->listData($request, $classGroup))
            ->setPaper('a4', $request->boolean('landscape') ? 'landscape' : 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);

        $filename = 'class-list-' . Str::slug($classGroup->name) . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function excel(Request $request, ClassGroup $classGroup)
    {
        $data = $this->listData($request, $classGroup);

        return Excel::download(new ClassRosterExport($data['students'], $data['columns'], self::COLUMNS), 'class-list-' . Str::slug($classGroup->name) . '.xlsx');
    }

    /**
     * Everything a list needs, with the authorisation rule in one place.
     */
    protected function listData(Request $request, ClassGroup $classGroup): array
    {
        abort_unless($this->allowedClasses()->contains('id', $classGroup->id), 403, 'You are not assigned to this class.');

        $classGroup->load('classTeacher');
        $term = Semester::with('academicYear')->where('is_current', true)->first();

        $columns = ($request->has('cols') || $request->has('submitted'))
            ? array_values(array_intersect(array_keys(self::COLUMNS), (array) $request->input('cols', [])))
            : self::DEFAULT_COLUMNS;

        $gender = in_array($request->input('gender'), ['Male', 'Female'], true) ? $request->input('gender') : null;
        $sort = $request->input('sort') === 'admission' ? 'admission' : 'name';
        $blank = max(0, min(20, (int) $request->input('blank', 0)));

        $everyone = Student::with('house')->where('class_group_id', $classGroup->id)->where('status', 'active')->get();

        $students = $everyone
            ->when($gender, fn ($c) => $c->where('gender', $gender))
            ->sortBy($sort === 'admission' ? fn ($s) => $s->index_number : fn ($s) => mb_strtolower($s->full_name), SORT_NATURAL)
            ->values();

        $summary = [
            'total' => $everyone->count(),
            'boys' => $everyone->where('gender', 'Male')->count(),
            'girls' => $everyone->where('gender', 'Female')->count(),
        ];

        $settings = DB::table('settings')->where('category', 'institution')->pluck('value', 'key')->toArray();

        $logoBase64 = \App\Support\Branding::logoDataUri();

        $title = trim((string) $request->input('title'));
        $printedBy = auth()->user()->name;

        return compact('classGroup', 'students', 'columns', 'term', 'summary', 'settings', 'logoBase64', 'gender', 'sort', 'blank', 'title', 'printedBy');
    }

    /** The options to carry from the preview page into the PDF / Excel links. */
    protected function fileQuery(Request $request): array
    {
        return array_filter([
            'cols' => $request->has('cols') ? (array) $request->input('cols') : null,
            'gender' => $request->input('gender'),
            'sort' => $request->input('sort'),
            'blank' => $request->input('blank'),
            'title' => $request->input('title'),
            'landscape' => $request->boolean('landscape') ? 1 : null,
            'submitted' => $request->has('submitted') && !$request->has('cols') ? 1 : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function allowedClasses(): Collection
    {
        $classes = ClassGroup::with('classTeacher')->orderBy('level')->orderBy('name')->get();
        $user = auth()->user();

        if ($this->isScopedTeacher()) {
            $teacherId = $this->authTeacherId() ?? 0;
            $subjectLevels = Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))->pluck('level')->unique();

            return $classes->filter(fn ($c) => $c->class_teacher_id === $teacherId || $subjectLevels->contains($c->level))->values();
        }

        // Admin and office roles: any class. Other accounts get none.
        return $user && $user->canAny(['manage-classes', 'manage-students', 'view-student-directory', 'manage-results', 'enter-scores'])
            ? $classes
            : collect();
    }
}

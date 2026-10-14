<?php

namespace App\Http\Controllers;

use App\Exports\TeacherExport;
use App\Exports\TeacherTemplateExport;
use App\Imports\TeacherImport;
use App\Models\Subject;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\Semester;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $teachers = $this->buildTeacherQuery($request)
            ->orderBy('name')->paginate(20)->withQueryString();

        $this->attachWorkload($teachers);

        $departments = Department::orderBy('name')->get();
        $subjects = Subject::orderBy('code')->get();

        return view('teachers.index', compact('teachers', 'departments', 'subjects'));
    }

    /**
     * Export the current filtered teacher list to Excel.
     */
    public function exportExcel(Request $request)
    {
        $teachers = $this->buildTeacherQuery($request)->orderBy('name')->get();
        $this->attachWorkload($teachers);

        return Excel::download(new TeacherExport($teachers), 'teachers.xlsx');
    }

    /**
     * Export the current filtered teacher list to PDF.
     */
    public function exportPdf(Request $request)
    {
        $teachers = $this->buildTeacherQuery($request)->orderBy('name')->get();
        $this->attachWorkload($teachers);

        $pdf = PDF::loadView('teachers.export-pdf', compact('teachers'))->setPaper('a4', 'landscape');

        return $pdf->download('teachers.pdf');
    }

    /**
     * Build the filtered teacher query shared by the on-screen list and both exports.
     */
    protected function buildTeacherQuery(Request $request)
    {
        $query = Teacher::withCount('subjects')->with('department');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('staff_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('subjects', fn ($q) => $q->where('subjects.id', $request->subject_id));
        }

        return $query;
    }

    /**
     * Attach each teacher's current-semester timetable workload (classes taught and total
     * credit hours) via a single grouped query, rather than querying per teacher.
     */
    protected function attachWorkload($teachers): void
    {
        $teacherIds = collect($teachers instanceof \Illuminate\Pagination\LengthAwarePaginator ? $teachers->items() : $teachers)
            ->pluck('id');

        if ($teacherIds->isEmpty()) {
            return;
        }

        $currentSemester = Semester::where('is_current', true)->first();

        $workloads = TimetableEntry::join('subjects', 'subjects.id', '=', 'timetable_entries.subject_id')
            ->whereIn('timetable_entries.teacher_id', $teacherIds)
            ->when($currentSemester, fn ($q) => $q->where('timetable_entries.semester_id', $currentSemester->id))
            ->selectRaw('timetable_entries.teacher_id, COUNT(*) as classes, SUM(subjects.credit_hours) as workload')
            ->groupBy('timetable_entries.teacher_id')
            ->get()
            ->keyBy('teacher_id');

        foreach ($teachers as $teacher) {
            $teacher->workload_classes = (int) ($workloads[$teacher->id]->classes ?? 0);
            $teacher->workload_credit = (float) ($workloads[$teacher->id]->workload ?? 0);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('teachers.create', $this->formData() + ['suggestedStaffId' => $this->nextStaffId()]);
    }

    /**
     * Everything the add / edit form needs: departments, the classes a teacher could be class
     * teacher of (those without one, plus this teacher's own) and subjects grouped by class level.
     */
    protected function formData(?Teacher $teacher = null): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'classOptions' => \App\Models\ClassGroup::with('classTeacher')
                ->where(fn ($q) => $q->whereNull('class_teacher_id')->when($teacher, fn ($q2) => $q2->orWhere('class_teacher_id', $teacher->id)))
                ->orderBy('level')->orderBy('name')->get(),
            'subjectsByLevel' => Subject::orderBy('level')->orderBy('title')->get()->groupBy('level'),
            'currentClassIds' => $teacher ? $teacher->classesInCharge()->pluck('id')->all() : [],
            'currentSubjectIds' => $teacher ? $teacher->subjects()->pluck('subjects.id')->all() : [],
        ];
    }

    /**
     * The next free staff ID in the school's TCH001 pattern.
     */
    protected function nextStaffId(): string
    {
        $highest = Teacher::where('staff_id', 'like', 'TCH%')->pluck('staff_id')
            ->map(fn ($id) => (int) preg_replace('/\D/', '', $id))->max() ?? 0;

        do {
            $highest++;
            $id = 'TCH' . str_pad((string) $highest, 3, '0', STR_PAD_LEFT);
        } while (Teacher::where('staff_id', $id)->exists());

        return $id;
    }

    /**
     * Ghana mobile numbers as the SMS gateway wants them: digits only, leading 0.
     */
    protected function cleanPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            $digits = '0' . substr($digits, 3);
        } elseif (strlen($digits) === 9) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * Teachers who look like the one being added (same email, phone, staff ID or name words), as
     * JSON for the add form's "already on record" warning.
     */
    public function duplicates(Request $request)
    {
        $words = collect(preg_split('/\s+/', trim((string) $request->query('name', ''))))->filter(fn ($w) => mb_strlen($w) >= 2)->take(3);
        $email = trim((string) $request->query('email', ''));
        $phone = $this->cleanPhone($request->query('phone'));
        $staffId = trim((string) $request->query('staff_id', ''));

        if ($words->count() < 2 && $email === '' && !$phone && $staffId === '') {
            return response()->json([]);
        }

        $matches = Teacher::query()
            ->when($request->filled('except'), fn ($q) => $q->where('id', '!=', (int) $request->except))
            ->where(function ($q) use ($words, $email, $phone, $staffId) {
                if ($words->count() >= 2) {
                    $q->orWhere(function ($w) use ($words) {
                        foreach ($words as $word) {
                            $w->where('name', 'like', "%{$word}%");
                        }
                    });
                }
                $email !== '' && $q->orWhere('email', $email);
                $phone && $q->orWhere('phone', $phone);
                $staffId !== '' && $q->orWhere('staff_id', $staffId);
            })
            ->limit(5)->get();

        return response()->json($matches->map(fn (Teacher $t) => [
            'name' => $t->name,
            'staff_id' => $t->staff_id,
            'why' => collect([
                $email !== '' && $t->email === $email ? 'same email' : null,
                $phone && $t->phone === $phone ? 'same phone' : null,
                $staffId !== '' && $t->staff_id === $staffId ? 'same staff ID' : null,
            ])->filter()->implode(', ') ?: 'similar name',
            'url' => route('teachers.show', $t),
        ])->values());
    }

    /**
     * Apply the form's teaching assignments: the classes this teacher is class teacher of, and
     * the subjects they teach.
     */
    protected function syncAssignments(Teacher $teacher, Request $request): void
    {
        $classIds = array_map('intval', (array) $request->input('class_group_ids', []));

        // Free the classes they no longer lead, then take the ticked ones that have no other class teacher.
        \App\Models\ClassGroup::where('class_teacher_id', $teacher->id)->whereNotIn('id', $classIds ?: [0])->update(['class_teacher_id' => null]);
        \App\Models\ClassGroup::whereIn('id', $classIds ?: [0])->whereNull('class_teacher_id')->update(['class_teacher_id' => $teacher->id]);

        $teacher->subjects()->sync(array_map('intval', (array) $request->input('subject_ids', [])));
    }

    /**
     * Give a teacher a portal login (role Teacher) with a temporary password.
     *
     * @return string the flash message, including the temporary password
     */
    protected function makeLogin(Teacher $teacher, PastechSmsService $sms, bool $textIt): string
    {
        $temporaryPassword = Str::password(12);

        User::create([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'password' => Hash::make($temporaryPassword),
            'role' => 'admin',
            'teacher_id' => $teacher->id,
            'first_login' => true,
        ])->assignRole('Teacher');

        $message = "Login created. Temporary password: {$temporaryPassword} (share this with the teacher securely - it will not be shown again).";

        return $message . ' ' . ($textIt
            ? $this->textTemporaryPassword($sms, $teacher, $temporaryPassword, 'Your account on the school portal has been created.')
            : 'No SMS was sent.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, PastechSmsService $sms)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('teachers.create')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['name', 'email', 'phone', 'staff_id', 'department_id']);
        $data['phone'] = $this->cleanPhone($data['phone'] ?? null);
        $data['staff_id'] = ($data['staff_id'] ?? '') !== '' ? $data['staff_id'] : $this->nextStaffId();

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('lecturer-photos', 'public');
        }

        $teacher = Teacher::create($data);
        $this->syncAssignments($teacher, $request);

        $message = "{$teacher->name} added (staff ID {$teacher->staff_id}).";

        if ($request->boolean('create_login')) {
            $message .= $teacher->email
                ? ' ' . $this->makeLogin($teacher, $sms, $request->boolean('send_sms') && (bool) $teacher->phone)
                : ' No login was created because no email address was given.';
        }

        return redirect()->route($request->input('after') === 'another' ? 'teachers.create' : 'teachers.show', $request->input('after') === 'another' ? [] : $teacher)
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher)
    {
        $teacher->load(['department', 'subjects.semester', 'timetableEntries.classGroup', 'timetableEntries.venue', 'timetableEntries.semester', 'user']);

        $workloadBySemester = TimetableController::workloadBySemesterForTeacher($teacher->id);

        return view('teachers.show', compact('teacher', 'workloadBySemester'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher)
    {
        return view('teachers.edit', $this->formData($teacher) + ['teacher' => $teacher]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Teacher $teacher)
    {
        $validator = Validator::make($request->all(), $this->rules($teacher->id));

        if ($validator->fails()) {
            return redirect()->route('teachers.edit', $teacher)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['name', 'email', 'phone', 'staff_id', 'department_id']);
        $data['phone'] = $this->cleanPhone($data['phone'] ?? null);

        if ($request->has('assignments_submitted')) {
            $this->syncAssignments($teacher, $request);
        }

        if ($request->hasFile('profile_photo')) {
            if ($teacher->profile_photo) {
                Storage::disk('public')->delete($teacher->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')->store('lecturer-photos', 'public');
        }

        $teacher->update($data);

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Teacher $teacher)
    {
        if ($teacher->subjects()->count() > 0) {
            return redirect()->route('teachers.index')
                ->with('error', 'Cannot delete a teacher who is assigned to one or more subjects.');
        }

        if ($teacher->timetableEntries()->count() > 0) {
            return redirect()->route('teachers.index')
                ->with('error', 'Cannot delete a teacher who is assigned to one or more timetable entries.');
        }

        if ($teacher->classesInCharge()->exists()) {
            return redirect()->route('teachers.index')
                ->with('error', 'Cannot delete a teacher who is the class teacher of a class. Give the class another class teacher first.');
        }

        // Their portal login goes with them - otherwise it would stay behind as a staff account
        // that is no longer linked to anyone.
        $teacher->user?->delete();

        if ($teacher->profile_photo) {
            Storage::disk('public')->delete($teacher->profile_photo);
        }

        $teacher->delete();

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted successfully.');
    }

    /**
     * Create a portal login for the teacher, and text the temporary password to their
     * phone if one is on file.
     */
    public function createUserAccount(Teacher $teacher, PastechSmsService $sms)
    {
        if ($teacher->user) {
            return redirect()->route('teachers.show', $teacher)
                ->with('warning', 'This teacher already has a user account.');
        }

        if (!$teacher->email) {
            return redirect()->route('teachers.show', $teacher)
                ->with('error', 'This teacher has no email address on file. Add one before creating a login.');
        }

        $temporaryPassword = Str::password(12);

        try {
            User::create([
                'name' => $teacher->name,
                'email' => $teacher->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'admin',
                'teacher_id' => $teacher->id,
                'first_login' => true,
            ])->assignRole('Teacher');

            $message = "User account created successfully. Temporary password: {$temporaryPassword} (share this with the teacher securely — it will not be shown again).";
            $message .= ' ' . $this->textTemporaryPassword($sms, $teacher, $temporaryPassword, 'Your account on the school portal has been created.');

            return redirect()->route('teachers.show', $teacher)->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('teachers.show', $teacher)
                ->with('error', 'Failed to create user account. Error: ' . $e->getMessage());
        }
    }

    /**
     * Reset a teacher's portal password to a new temporary one and text it to their phone.
     */
    public function resetPassword(Teacher $teacher, PastechSmsService $sms)
    {
        if (!$teacher->user) {
            return redirect()->route('teachers.show', $teacher)
                ->with('error', 'This teacher has no login account yet. Create one first.');
        }

        $temporaryPassword = Str::password(12);

        $teacher->user->update([
            'password' => Hash::make($temporaryPassword),
            'first_login' => true,
        ]);

        $message = "Password reset successfully. Temporary password: {$temporaryPassword} (share this with the teacher securely — it will not be shown again).";
        $message .= ' ' . $this->textTemporaryPassword($sms, $teacher, $temporaryPassword, 'Your password on the school portal has been reset.');

        return redirect()->route('teachers.show', $teacher)->with('success', $message);
    }

    /**
     * Text a temporary password to a teacher's phone, if one is on file. Returns a short
     * status string to append to the admin-facing flash message - the password is always
     * shown on screen too, so a missing/failed SMS never leaves the admin without it.
     */
    protected function textTemporaryPassword(PastechSmsService $sms, Teacher $teacher, string $temporaryPassword, string $intro): string
    {
        if (!$teacher->phone) {
            return 'No phone number on file, so no SMS was sent.';
        }

        $result = $sms->sendSms(
            $teacher->phone,
            "{$intro} Temporary password: {$temporaryPassword}. Please log in and change it immediately."
        );

        return $result['success']
            ? "SMS sent to {$teacher->phone}."
            : "SMS to {$teacher->phone} failed: {$result['message']}.";
    }

    /**
     * Show the bulk teacher import form.
     */
    public function importForm()
    {
        return view('teachers.import');
    }

    /**
     * Handle the bulk import of teachers from Excel/CSV.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('teachers.import.form')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $import = new TeacherImport();
            Excel::import($import, $request->file('excel_file'));

            $stats = $import->getStats();
            $message = "Processed {$stats['processed']} record(s), skipped {$stats['skipped']}.";

            if (!empty($stats['errors'])) {
                $message .= ' Issues: ' . implode(' | ', array_slice($stats['errors'], 0, 5));
                if (count($stats['errors']) > 5) {
                    $message .= ' (+' . (count($stats['errors']) - 5) . ' more)';
                }

                return redirect()->route('teachers.index')->with('warning', $message);
            }

            return redirect()->route('teachers.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('teachers.import.form')
                ->with('error', 'Error importing teachers: ' . $e->getMessage());
        }
    }

    /**
     * Download the teacher import template.
     */
    public function downloadTemplate()
    {
        return Excel::download(new TeacherTemplateExport, 'teachers_template.xlsx');
    }

    /**
     * Shared validation rules for store/update.
     */
    protected function rules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($ignoreId)],
            'phone' => 'nullable|string|max:20',
            'staff_id' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'staff_id')->ignore($ignoreId)],
            'department_id' => 'nullable|exists:departments,id',
            'profile_photo' => 'nullable|image|max:2048',
            'class_group_ids' => 'nullable|array',
            'class_group_ids.*' => 'exists:class_groups,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ];
    }
}

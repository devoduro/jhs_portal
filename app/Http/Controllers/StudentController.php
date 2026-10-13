<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Programme;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Imports\StudentsImport;
use App\Exports\StudentsExport;
use App\Services\GradeNotificationService;
use App\Services\PastechSmsService;
use App\Services\StudentPdfExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    /**
     * Export students list to Excel
     */
    public function exportExcel(Request $request)
    {
        return Excel::download(new StudentsExport($request), 'students.xlsx');
    }

    /**
     * Export students list to PDF
     */
    public function exportPdf(Request $request)
    {
        $exporter = new StudentPdfExport($request);
        return $exporter->export();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::with('classGroup');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('index_number', 'like', "%{$searchTerm}%")
                    ->orWhere('emergency_contact_name', 'like', "%{$searchTerm}%")
                    ->orWhere('emergency_contact_phone', 'like', "%{$searchTerm}%")
                    ->orWhere('phone', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->filled('class_group_id')) {
            $query->where('class_group_id', $request->class_group_id);
        }

        if ($request->boolean('unplaced')) {
            $query->whereNull('class_group_id');
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $query->where('status', $request->input('status', 'active'));

        $students = $query->orderBy('level')->orderBy('full_name')->paginate(25)->withQueryString();
        $classGroups = $this->classGroupOptions();

        return view('students.index', compact('students', 'classGroups'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $classGroups = $this->classGroupOptions();
        $suggestedAdmissionNumber = $this->nextAdmissionNumber();
        $classInfo = $this->classInfo($classGroups);
        $currentTerm = \App\Models\Semester::where('is_current', true)->first();

        return view('students.create', compact('classGroups', 'suggestedAdmissionNumber', 'classInfo', 'currentTerm'));
    }

    /**
     * What the picker shows next to each class: pupils on roll and the current term's fees
     * for that class level, so the person admitting knows what the pupil will be billed.
     *
     * @return array<int, array{roll: int, fees: float, level: int}>
     */
    protected function classInfo($classGroups): array
    {
        $term = \App\Models\Semester::where('is_current', true)->first();
        $fees = $term ? \App\Models\FeeStructure::where('semester_id', $term->id)->selectRaw('level, SUM(amount) as total')->groupBy('level')->pluck('total', 'level') : collect();
        $rolls = Student::where('status', 'active')->selectRaw('class_group_id, COUNT(*) as n')->groupBy('class_group_id')->pluck('n', 'class_group_id');

        return $classGroups->mapWithKeys(fn ($c) => [$c->id => [
            'roll' => (int) ($rolls[$c->id] ?? 0),
            'fees' => (float) ($fees[$c->level] ?? 0),
            'level' => (int) $c->level,
        ]])->all();
    }

    /**
     * Pupils who look like the one being admitted (same name words), so the same child is not
     * admitted twice. JSON for the admit form.
     */
    public function duplicates(Request $request)
    {
        $words = collect(preg_split('/\s+/', trim((string) $request->query('name', ''))))->filter(fn ($w) => mb_strlen($w) >= 2)->take(3);

        if ($words->count() < 2) {
            return response()->json([]);
        }

        $matches = Student::with('classGroup')
            ->when($request->filled('except'), fn ($q) => $q->where('id', '!=', (int) $request->except))
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where('full_name', 'like', "%{$word}%");
                }
            })
            ->orderByRaw("status = 'active' DESC")->limit(5)->get();

        return response()->json($matches->map(fn (Student $s) => [
            'name' => $s->full_name,
            'admission' => $s->index_number,
            'class' => $s->classGroup->name ?? \App\Support\FormLabel::label($s->level),
            'dob' => $s->date_of_birth?->format('d M Y'),
            'status' => $s->status,
            'url' => route('students.show', $s),
        ])->values());
    }

    /**
     * Find a pupil whose parent/guardian details can be copied onto a brother or sister being
     * admitted. JSON for the admit form.
     */
    public function family(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $pupils = Student::with('classGroup')
            ->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")->orWhere('index_number', 'like', "%{$term}%")
                ->orWhere('emergency_contact_name', 'like', "%{$term}%")->orWhere('emergency_contact_phone', 'like', "%{$term}%"))
            ->orderByRaw("status = 'active' DESC")->orderBy('full_name')->limit(6)->get();

        return response()->json($pupils->map(fn (Student $s) => [
            'name' => $s->full_name,
            'class' => $s->classGroup->name ?? \App\Support\FormLabel::label($s->level),
            'parent' => [
                'emergency_contact_name' => $s->emergency_contact_name,
                'emergency_contact_relationship' => $s->emergency_contact_relationship,
                'emergency_contact_phone' => $s->emergency_contact_phone,
                'parent_email' => $s->parent_email,
                'phone' => $s->phone,
                'address' => $s->address,
                'hometown' => $s->hometown,
                'gps_address' => $s->gps_address,
            ],
        ])->values());
    }

    /**
     * Ghana mobile numbers as the SMS gateway wants them: digits only, leading 0
     * (233 24 123 4567 becomes 0241234567).
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

    protected function classGroupOptions()
    {
        return ClassGroup::orderBy('level')->orderBy('name')->get();
    }

    /**
     * Next admission number in the school's pattern, e.g. "SCH/2026/0042": institution code,
     * year of admission and a running number for that year.
     */
    protected function nextAdmissionNumber(): string
    {
        $code = \App\Models\Setting::get('institution_code', 'SCH') ?: 'SCH';
        $prefix = strtoupper($code) . '/' . now()->year . '/';

        $last = Student::where('index_number', 'like', $prefix . '%')
            ->pluck('index_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validation shared by create and update. A pupil's class level comes from their class
     * when one is chosen, so level is only required for a pupil not yet placed in a class.
     */
    protected function pupilRules(?int $ignoreId = null): array
    {
        return [
            'index_number' => 'nullable|string|max:50|unique:students,index_number' . ($ignoreId ? ',' . $ignoreId : ''),
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'required|in:Male,Female',
            'class_group_id' => 'nullable|exists:class_groups,id',
            'level' => 'required_without:class_group_id|nullable|integer|' . \App\Support\FormLabel::rule(),
            'status' => 'nullable|in:active,graduated,withdrawn',
            'profile_photo' => 'nullable|image|max:2048',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'parent_email' => 'nullable|email|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'hometown' => 'nullable|string|max:255',
            'gps_address' => 'nullable|string|max:50',
        ];
    }

    protected function pupilData(Request $request): array
    {
        $data = $request->only([
            'index_number', 'full_name', 'date_of_birth', 'gender', 'class_group_id', 'level', 'status',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'parent_email',
            'email', 'phone', 'address', 'hometown', 'gps_address',
        ]);

        foreach (['level', 'date_of_birth', 'index_number', 'status'] as $field) {
            if (($data[$field] ?? '') === '') {
                unset($data[$field]);
            }
        }

        foreach (['emergency_contact_phone', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanPhone($data[$field]);
            }
        }

        if (($data['class_group_id'] ?? '') === '') {
            $data['class_group_id'] = null;
        } else {
            $data['level'] = ClassGroup::find($data['class_group_id'])->level;
        }

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->pupilRules());

        if ($validator->fails()) {
            return redirect()->route('students.create')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $this->pupilData($request);
        $data['index_number'] = $data['index_number'] ?? $this->nextAdmissionNumber();
        $data['status'] = 'active';

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        $student = Student::create($data);
        $message = "{$student->full_name} admitted with admission number {$student->index_number}.";

        // Optionally put this term's fees on the new pupil's account straight away.
        if ($request->boolean('bill_now') && $student->level) {
            $term = \App\Models\Semester::where('is_current', true)->first();
            $stats = $term ? \App\Services\FeeLedgerService::billTerm($term, collect([$student])) : null;
            $message .= $stats && $stats['billed']
                ? ' ' . $term->name . ' fees of GH₵ ' . number_format($stats['total'], 2) . ' billed.'
                : ' (No fees are set for their class this term, so nothing was billed.)';
        }

        if ($request->input('after') === 'another') {
            return redirect()->route('students.create')->with('success', $message);
        }

        return redirect()->route('students.show', $student)->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = Student::with(['classGroup', 'results' => function($query) {
            $query->with(['subject', 'semester', 'academicYear'])
                  ->orderBy('academic_year_id')
                  ->orderBy('semester_id');
        }])->findOrFail($id);

        // Group results by academic year and semester
        $groupedResults = [];
        foreach ($student->results as $result) {
            $academicYearId = $result->academic_year_id;
            $semesterId = $result->semester_id;

            if (!isset($groupedResults[$academicYearId])) {
                $groupedResults[$academicYearId] = [
                    'academic_year' => $result->academicYear,
                    'semesters' => []
                ];
            }

            if (!isset($groupedResults[$academicYearId]['semesters'][$semesterId])) {
                $groupedResults[$academicYearId]['semesters'][$semesterId] = [
                    'semester' => $result->semester,
                    'results' => [],
                    'aggregate' => 0
                ];
            }

            $groupedResults[$academicYearId]['semesters'][$semesterId]['results'][] = $result;
        }

        // Calculate WASSCE aggregate for each semester
        foreach ($groupedResults as &$academicYear) {
            foreach ($academicYear['semesters'] as $semesterId => &$semesterData) {
                $semesterData['aggregate'] = $student->calculateAggregate($semesterId);
            }
        }

        $semesters = Semester::with('academicYear')->orderBy('academic_year_id', 'desc')->orderBy('semester_number', 'desc')->get();
        $balance = $student->outstandingBalance();

        return view('students.show', compact('student', 'groupedResults', 'semesters', 'balance'));
    }

    /**
     * Text a student's emergency contact a summary of their results for a semester,
     * plus a link to the full report card.
     */
    public function sendGrades(Request $request, string $id, PastechSmsService $sms)
    {
        $student = Student::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:semesters,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('students.show', $student)
                ->with('error', 'Select a term to send the report for.');
        }

        if (!$student->emergency_contact_phone) {
            return redirect()->route('students.show', $student)
                ->with('error', 'This pupil has no parent/guardian phone number on file.');
        }

        $semester = Semester::findOrFail($request->semester_id);
        $smsLogId = GradeNotificationService::sendToParent($student, $semester, $sms);

        if (!$smsLogId) {
            return redirect()->route('students.show', $student)
                ->with('error', 'No scores found for that term, or the message could not be sent.');
        }

        return redirect()->route('students.show', $student)
            ->with('success', "{$semester->name} report link sent to {$student->full_name}'s parent/guardian.");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $student = Student::findOrFail($id);
        $classGroups = $this->classGroupOptions();

        return view('students.edit', compact('student', 'classGroups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $student = Student::findOrFail($id);

        $validator = Validator::make($request->all(), $this->pupilRules((int) $id));

        if ($validator->fails()) {
            return redirect()->route('students.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $this->pupilData($request);

        if ($request->hasFile('profile_photo')) {
            if ($student->profile_photo) {
                Storage::disk('public')->delete($student->profile_photo);
            }

            $data['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        $student->update($data);

        return redirect()->route('students.show', $student)
            ->with('success', 'Pupil details updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $student = Student::findOrFail($id);
        
        // Delete profile photo if exists
        if ($student->profile_photo) {
            Storage::disk('public')->delete($student->profile_photo);
        }
        
        $student->delete();

        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }
    
    /**
     * Show the form for importing students from Excel.
     */
    public function importForm()
    {
        return view('students.import');
    }
    
    /**
     * Import students from Excel file.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('students.import.form')
                ->withErrors($validator)
                ->withInput();
        }
        
        if (!class_exists('ZipArchive')) {
            return redirect()->route('students.import.form')
                ->with('error', 'The PHP zip extension is not loaded. Please restart the PHP server and try again. If the problem persists, enable extension=zip in C:\xampp\php\php.ini and restart.');
        }

        try {
            $import = new StudentsImport();
            Excel::import($import, $request->file('excel_file'));

            $message = "{$import->created} pupil(s) admitted, {$import->updated} updated.";

            if ($import->errors) {
                $message .= ' Skipped: ' . implode(' ', array_slice($import->errors, 0, 5))
                    . (count($import->errors) > 5 ? ' (+' . (count($import->errors) - 5) . ' more)' : '');

                return redirect()->route('students.index')->with('warning', $message);
            }

            return redirect()->route('students.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('students.import.form')
                ->with('error', 'Error importing students: ' . $e->getMessage());
        }
    }
    
    /**
     * Download sample Excel template for student import.
     */
    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\StudentImportTemplateExport, 'pupils_import_template.xlsx');
    }
    
    /**
     * Create a user account for a student.
     */
    public function createUserAccount(string $id)
    {
        $student = Student::findOrFail($id);
        
        // Check if student already has a user account
        if ($student->user) {
            return redirect()->route('students.show', $id)
                ->with('warning', 'Student already has a user account.');
        }
        
        // Format date of birth as YYYYMMDD for default password
        $defaultPassword = date('Ymd', strtotime($student->date_of_birth));
        
        try {
            // Create user account
            $user = User::create([
                'name' => $student->full_name,
                'email' => $student->email ?? $student->index_number . '@st.uew.edu.gh',
                'password' => Hash::make($defaultPassword),
                'role' => 'student',
                'index_number' => $student->index_number,
                'student_id' => $student->id,
                'first_login' => true
            ]);
            
            return redirect()->route('students.show', $id)
                ->with('success', 'User account created successfully. Default password is the student\'s is password.');
        } catch (\Exception $e) {
            return redirect()->route('students.show', $id)
                ->with('error', 'Failed to create user account. Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create user accounts for all students without user accounts.
     */
    public function bulkCreateUserAccounts(Request $request)
    {
        $created = 0;
        $errors = [];
        
        $students = Student::whereDoesntHave('user')->get();
        
        foreach ($students as $student) {
            try {
                // Format date of birth as YYYYMMDD for default password
                $defaultPassword = "password";
                
                // Create user account
                User::create([
                    'name' => $student->full_name,
                    'email' => $student->email ?? $student->index_number . '@st.uew.edu.gh',
                    'password' => Hash::make($defaultPassword),
                    'role' => 'student',
                    'index_number' => $student->index_number,
                    'student_id' => $student->id,
                    'first_login' => true
                ]);
                
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Failed to create account for {$student->index_number}: {$e->getMessage()}";
            }
        }
        
        if ($created > 0) {
            $message = "Created {$created} user accounts. Default password is each student's date of birth (YYYYMMDD).";
            if (!empty($errors)) {
                $message .= "\n\nErrors:\n" . implode("\n", $errors);
            }
            return redirect()->route('students.index')->with('success', $message);
        } else {
            return redirect()->route('students.index')
                ->with('error', "No user accounts were created.\n\nErrors:\n" . implode("\n", $errors));
        }
    }

    /**
     * Reset all student passwords to a default password.
     */
    public function bulkResetPasswords(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->route('students.index')
                ->withErrors($validator)
                ->withInput();
        }

        // Increase execution time and memory for bulk operation
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '512M');

        $defaultPassword = $request->input('default_password');
        $updated = 0;
        $errors = [];
        
        // Hash the password once instead of for each user
        $hashedPassword = Hash::make($defaultPassword);
        
        // Get all users with student role
        $users = User::where('role', 'student')->get();
        
        foreach ($users as $user) {
            try {
                $user->password = $hashedPassword;
                $user->first_login = true;
                $user->save();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to reset password for user ID {$user->id}: {$e->getMessage()}";
            }
        }
        
        if ($updated > 0) {
            $message = "Successfully reset passwords for {$updated} student(s). Default password: {$defaultPassword}";
            if (!empty($errors)) {
                $message .= " | Errors: " . count($errors) . " failed.";
            }
            return redirect()->route('students.index')->with('success', $message);
        } else {
            return redirect()->route('students.index')
                ->with('error', "No passwords were reset. " . implode(", ", $errors));
        }
    }

    /**
     * Display the student's results.
     */
    public function results(string $id)
    {
        // Scores are listed term by term on the pupil's profile.
        return redirect()->route('students.show', $id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeScheme;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $academicYears = AcademicYear::all();
        $gradeSchemes = GradeScheme::all();

        $currentAcademicYear = AcademicYear::where('is_current', true)->first();
        $currentSemester = Semester::where('is_current', true)->first();
        $institutionName = \App\Models\Setting::get('institution_name');
        $totalStudents = \App\Models\Student::where('status', 'active')->count();
        $classCount = \App\Models\ClassGroup::count();
        $subjectCount = \App\Models\Subject::count();

        $lastBackupTime = collect(glob(storage_path('app/backups/*')) ?: [])->filter(fn ($f) => is_file($f))->map(fn ($f) => filemtime($f))->max();
        $lastBackup = $lastBackupTime ? \Carbon\Carbon::createFromTimestamp($lastBackupTime) : null;

        return view('settings.index', compact(
            'classCount',
            'subjectCount',
            'lastBackup',
            'academicYears',
            'gradeSchemes',
            'currentAcademicYear',
            'currentSemester',
            'institutionName',
            'totalStudents'
        ));
    }
    
    /**
     * Display academic years settings.
     */
    public function academicYears()
    {
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('settings.academic_years.index', compact('academicYears'));
    }
    
    /**
     * Display grade schemes settings.
     */
    public function gradeSchemes()
    {
        $gradeSchemes = GradeScheme::all();
        return view('settings.grade_schemes', compact('gradeSchemes'));
    }
    
    /**
     * Show the form for creating a new academic year.
     */
    public function createAcademicYear()
    {
        $existingYears = AcademicYear::pluck('name')->toArray();
        return view('settings.academic_years.create', compact('existingYears'));
    }

    /**
     * Store a newly created academic year.
     */
    public function storeAcademicYear(Request $request)
    {
        // Prepare data with explicit boolean casting for is_current
        $data = $request->all();
        $data['is_current'] = $request->has('is_current') ? true : false;

        // Validate academic year data
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|unique:academic_years,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'semesters' => 'required|array|min:1',
            'semesters.*.semester_number' => 'required|integer|min:1',
            'semesters.*.name' => 'required|string|max:255',
            'semesters.*.start_date' => 'required|date',
            'semesters.*.end_date' => 'required|date|after:semesters.*.start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.academic-years.create')
                ->withErrors($validator)
                ->withInput();
        }
        
        // Start database transaction
        DB::beginTransaction();
        
        try {
            // If this is set as current, unset all others
            if ($data['is_current']) {
                AcademicYear::where('is_current', true)->update(['is_current' => false]);
            }
            
            // Create academic year
            $academicYear = AcademicYear::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => $data['is_current'],
            ]);
            
            // Create semesters
            if (!empty($data['semesters']) && is_array($data['semesters'])) {
                foreach ($data['semesters'] as $semesterData) {
                    // Skip if any required field is missing
                    if (!isset($semesterData['semester_number'], $semesterData['name'], 
                             $semesterData['start_date'], $semesterData['end_date'])) {
                        continue;
                    }
                    
                    // Create semester with explicit data
                    $semester = new Semester([
                        'semester_number' => $semesterData['semester_number'],
                        'name' => $semesterData['name'],
                        'start_date' => $semesterData['start_date'],
                        'end_date' => $semesterData['end_date'],
                        'is_current' => false, // Default to false
                    ]);
                    
                    $academicYear->semesters()->save($semester);
                }
            }
            
            // If no semesters were created, rollback
            if ($academicYear->semesters()->count() === 0) {
                DB::rollBack();
                return redirect()->route('settings.academic-years.create')
                    ->withInput()
                    ->with('error', 'At least one semester is required.');
            }
            
            DB::commit();
            
            return redirect()->route('settings.academic-years')
                ->with('success', 'Academic year and semesters created successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->route('settings.academic-years.create')
                ->withInput()
                ->with('error', 'Failed to create academic year: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new grade scheme.
     */
    public function createGradeScheme()
    {
        return view('settings.grade_schemes.create');
    }

    /**
     * Store a newly created grade scheme.
     */
    public function storeGradeScheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
            'grades' => 'required|array|min:1',
            'grades.*.grade' => 'required|string|max:5',
            'grades.*.min_score' => 'required|numeric|min:0|max:100',
            'grades.*.grade_value' => 'required|numeric|min:0|max:9',
            'grades.*.remark' => 'nullable|string|max:100',
            'section' => 'nullable|in:preschool,primary,jhs',
        ]);
        $this->checkGradeBands($validator, $request);

        if ($validator->fails()) {
            return redirect()->route('settings.grade-schemes.create')
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $gradeScheme = GradeScheme::create([
                'name' => $request->name,
                'description' => $request->description,
                'section' => $request->input('section') ?: null,
                'is_default' => $request->boolean('is_default', false),
            ]);

            // If this is set as default, remove default from others
            if ($gradeScheme->is_default) {
                GradeScheme::where('id', '!=', $gradeScheme->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Create grades
            $grades = collect($request->grades)->map(function ($grade) {
                return [
                    'grade' => $grade['grade'],
                    'min_score' => $grade['min_score'],
                    'grade_value' => $grade['grade_value'],
                    'remark' => $grade['remark'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            $gradeScheme->grades()->createMany($grades);

            DB::commit();
            return redirect()->route('settings.grade-schemes')
                ->with('success', 'Grade scheme created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('settings.grade-schemes.create')
                ->with('error', 'Failed to create grade scheme. ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Edit an academic year.
     */
    public function editAcademicYear(string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);
        return view('settings.academic_years.edit', compact('academicYear'));
    }
    
    /**
     * Update an academic year.
     */
    public function updateAcademicYear(Request $request, string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:academic_years,name,' . $id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.academic-years.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }
        
        // If this is set as current, unset all others
        if ($request->has('is_current') && $request->input('is_current')) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }
        
        $academicYear->update($request->all());
        
        return redirect()->route('settings.index')
            ->with('success', 'Academic year updated successfully.');
    }
    
    /**
     * Edit a grade scheme.
     */
    public function editGradeScheme(string $id)
    {
        $gradeScheme = GradeScheme::findOrFail($id);
        return view('settings.grade_schemes.edit', compact('gradeScheme'));
    }
    
    /**
     * Update a grade scheme.
     */
    public function updateGradeScheme(Request $request, string $id)
    {
        $gradeScheme = GradeScheme::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
            'grades' => 'required|array|min:1',
            'grades.*.grade' => 'required|string|max:5',
            'grades.*.min_score' => 'required|numeric|min:0|max:100',
            'grades.*.grade_value' => 'required|numeric|min:0|max:9',
            'grades.*.remark' => 'nullable|string|max:100',
            'section' => 'nullable|in:preschool,primary,jhs',
        ]);
        $this->checkGradeBands($validator, $request);

        if ($validator->fails()) {
            return redirect()->route('settings.grade-schemes.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $gradeScheme->update([
                'name' => $request->name,
                'description' => $request->description,
                'section' => $request->input('section') ?: null,
                'is_default' => $request->boolean('is_default', false),
            ]);

            // If this is set as default, remove default from others
            if ($gradeScheme->is_default) {
                GradeScheme::where('id', '!=', $gradeScheme->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // Delete existing grades
            $gradeScheme->grades()->delete();

            // Create new grades
            $grades = collect($request->grades)->map(function ($grade) {
                return [
                    'grade' => $grade['grade'],
                    'min_score' => $grade['min_score'],
                    'grade_value' => $grade['grade_value'],
                    'remark' => $grade['remark'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            $gradeScheme->grades()->createMany($grades);

            DB::commit();
            return redirect()->route('settings.grade-schemes')
                ->with('success', 'Grade scheme updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('settings.grade-schemes.edit', $id)
                ->with('error', 'Failed to update grade scheme. ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * A grading scale must give every possible score a grade: one band must start at 0 and no
     * two bands may share a starting score (the highest band a score reaches is the one used).
     */
    protected function checkGradeBands($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $mins = collect((array) $request->input('grades', []))->pluck('min_score')->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v);

            if ($mins->isEmpty()) {
                return;
            }
            if ($mins->min() > 0) {
                $validator->errors()->add('grades', 'One grade must start at 0, so that every score gets a grade.');
            }
            if ($mins->duplicates()->isNotEmpty()) {
                $validator->errors()->add('grades', 'Two grades start at the same score. Each grade needs its own starting score.');
            }
        });
    }

    /**
     * Delete an academic year.
     */
    public function destroyAcademicYear(string $id)
    {
        $academicYear = AcademicYear::findOrFail($id);

        if ($academicYear->is_current) {
            return redirect()->route('settings.index')
                ->with('error', 'This is the current academic year. Make another year current before deleting it.');
        }

        $termIds = $academicYear->semesters()->pluck('id');
        if (\App\Models\StudentFeeCharge::whereIn('semester_id', $termIds)->exists() || \App\Models\StudentPayment::whereIn('semester_id', $termIds)->exists()
            || \App\Models\TermReport::whereIn('semester_id', $termIds)->exists()) {
            return redirect()->route('settings.index')
                ->with('error', 'Cannot delete an academic year that has fees billed, payments received or report remarks entered.');
        }
        
        // Check if there are any results associated with this academic year
        if ($academicYear->results()->count() > 0) {
            return redirect()->route('settings.index')
                ->with('error', 'Cannot delete academic year with associated results.');
        }
        
        $academicYear->delete();
        
        return redirect()->route('settings.index')
            ->with('success', 'Academic year deleted successfully.');
    }
    
    /**
     * Delete a grade scheme.
     */
    public function destroyGradeScheme(string $id)
    {
        $gradeScheme = GradeScheme::findOrFail($id);

        if ($gradeScheme->is_default) {
            return redirect()->route('settings.grade-schemes')
                ->with('error', 'This is the default grade scheme. Make another scheme the default before deleting it.');
        }

        $gradeScheme->delete();

        return redirect()->route('settings.index')
            ->with('success', 'Grade scheme deleted successfully.');
    }

    /**
     * Mark a grade scheme as the default one used across the system.
     */
    public function setDefaultGradeScheme(string $id)
    {
        $gradeScheme = GradeScheme::findOrFail($id);

        DB::transaction(function () use ($gradeScheme) {
            GradeScheme::where('is_default', true)->update(['is_default' => false]);
            $gradeScheme->update(['is_default' => true]);
        });

        return redirect()->route('settings.grade-schemes')
            ->with('success', "\"{$gradeScheme->name}\" is now the default grade scheme.");
    }
    
    /**
     * Backup the database.
     */
    public function backupDatabase(Request $request)
    {
        try {
            $backupDir = storage_path('app/backups');

            if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
                throw new \Exception('The backups folder could not be created.');
            }

            $filename = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
            $target = $backupDir . DIRECTORY_SEPARATOR . $filename;

            $config = config('database.connections.mysql');
            $dump = env('MYSQLDUMP_PATH', 'mysqldump');

            // Every part is escaped; the password is only passed when there is one.
            $command = escapeshellarg($dump)
                . ' --user=' . escapeshellarg((string) $config['username'])
                . ($config['password'] !== null && $config['password'] !== '' ? ' --password=' . escapeshellarg((string) $config['password']) : '')
                . ' --host=' . escapeshellarg((string) $config['host'])
                . ' --port=' . escapeshellarg((string) ($config['port'] ?? 3306))
                . ' --single-transaction ' . escapeshellarg((string) $config['database'])
                . ' > ' . escapeshellarg($target) . ' 2>&1';

            exec($command, $output, $returnVar);

            if ($returnVar !== 0 || !is_file($target) || filesize($target) < 100) {
                @unlink($target);
                throw new \Exception('mysqldump did not run - check that it is installed, or set MYSQLDUMP_PATH in .env.');
            }

            return redirect()->route('settings.backup')
                ->with('success', "Database backup {$filename} created.");
        } catch (\Exception $e) {
            return redirect()->route('settings.backup')
                ->with('error', 'Database backup failed: ' . $e->getMessage());
        }
    }

    /**
     * List database backups.
     */
    public function backup()
    {
        $backupPath = storage_path('app/backups');
        $backups = [];
        
        // Initialize debug information with default values
        $debug = [
            'backup_path' => $backupPath,
            'backup_count' => 0,
            'directory_exists' => false,
            'directory_writable' => false,
            'directory_readable' => false,
            'directory_permissions' => 'N/A',
            'found_files' => '',
            'raw_files' => '',
            'php_user' => get_current_user(),
            'storage_path' => storage_path(),
            'public_path' => public_path()
        ];
        
        try {
            // Update directory status
            $debug['directory_exists'] = is_dir($backupPath);
            if ($debug['directory_exists']) {
                $debug['directory_writable'] = is_writable($backupPath);
                $debug['directory_readable'] = is_readable($backupPath);
                $debug['directory_permissions'] = substr(sprintf('%o', fileperms($backupPath)), -4);
            }
            
            // Ensure backup directory exists
            if (!Storage::exists('backups')) {
                Storage::makeDirectory('backups');
            }

            // Get all backup files
            $files = glob($backupPath . '/*.sql') ?: [];
            $debug['raw_files'] = implode(', ', $files);
            
            foreach ($files as $file) {
                if (is_file($file) && is_readable($file)) {
                    $filename = basename($file);
                    $backups[] = [
                        'filename' => $filename,
                        'size' => filesize($file),
                        'created_at' => filemtime($file),
                    ];
                }
            }
            
            // Sort backups by creation date (newest first)
            if (!empty($backups)) {
                usort($backups, function($a, $b) {
                    return $b['created_at'] - $a['created_at'];
                });
            }
            
            // Update backup-related debug info
            $debug['backup_count'] = count($backups);
            $debug['found_files'] = implode(', ', array_column($backups, 'filename'));
            
            return view('settings.backup', compact('backups', 'debug'));
            
        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['trace'] = $e->getTraceAsString();
            
            return view('settings.backup', [
                'backups' => [],
                'debug' => $debug
            ])->with('error', 'Error listing backups: ' . $e->getMessage());
        }
    }
    
    /**
     * Display system settings.
     */
    public function system()
    {
        // Get system settings from DB or config
        // The school's name and address live in the Institution Profile; show those, not a seeded placeholder.
        $settings = DB::table('settings')->whereIn('category', ['system', 'institution'])
            ->orderByRaw("category = 'institution' DESC")->get();
        
        return view('settings.system', compact('settings'));
    }
    
    /**
     * Update system settings.
     */
    public function updateSystem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'institution_name' => 'required|string|max:255',
            'institution_code' => 'required|string|max:50',
            'institution_address' => 'nullable|string',
            'institution_contact' => 'nullable|string',
            'transcript_prefix' => 'nullable|string|max:10',
            'transcript_footer' => 'nullable|string',
            'transcript_signature' => 'nullable|string|max:100',
            'transcript_watermark' => 'nullable|string|max:50',
            'email_from_address' => 'nullable|email',
            'email_from_name' => 'nullable|string|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->route('settings.system')
                ->withErrors($validator)
                ->withInput();
        }
        
        // Update or create settings in the database
        $fields = [
            'institution_name',
            'institution_code',
            'institution_address',
            'institution_contact',
            'transcript_prefix',
            'transcript_footer',
            'transcript_signature',
            'transcript_watermark',
            'email_from_address',
            'email_from_name',
        ];
        
        foreach ($fields as $field) {
            // Name and address are the same facts as on the Institution Profile - keep one copy.
            $category = in_array($field, ['institution_name', 'institution_address'], true) ? 'institution' : 'system';

            DB::table('settings')->updateOrInsert(
                ['key' => $field, 'category' => $category],
                ['value' => $request->input($field), 'updated_at' => now()]
            );
        }
        
        return redirect()->route('settings.system')->with('success', 'System settings updated successfully.');
    }
    

    

    
/**
 * School & report settings: which class levels the school runs, the class-score /
 * exam-score split on terminal reports, the admission-number prefix, and report options.
 */
public function school()
{
    $values = DB::table('settings')->pluck('value', 'key');

    return view('settings.school', [
        'offered' => \App\Support\FormLabel::offeredLevels(),
        'values' => $values,
    ]);
}

public function updateSchool(Request $request)
{
    $request->validate([
        'levels' => 'required|array|min:1',
        'levels.*' => 'integer|' . \App\Support\FormLabel::rule(),
        'class_score_max' => 'required|numeric|min:0|max:100',
        'exam_score_max' => 'required|numeric|min:0|max:100',
        'institution_code' => 'required|string|max:10|alpha_num',
        'head_title' => 'required|string|max:50',
        'report_show_fees' => 'nullable|boolean',
    ]);

    if ((float) $request->class_score_max + (float) $request->exam_score_max != 100) {
        return back()->withInput()->withErrors(['class_score_max' => 'Class score and exam score must add up to 100.']);
    }

    $levels = collect($request->levels)->map(fn ($l) => (int) $l)->sort()->implode(',');

    $values = [
        'levels_offered' => ['academic', $levels],
        'class_score_max' => ['academic', (string) (float) $request->class_score_max],
        'exam_score_max' => ['academic', (string) (float) $request->exam_score_max],
        'report_show_fees' => ['academic', $request->boolean('report_show_fees') ? '1' : '0'],
        'institution_code' => ['system', strtoupper($request->institution_code)],
        'head_title' => ['institution', $request->head_title],
    ];

    foreach ($values as $key => [$category, $value]) {
        DB::table('settings')->updateOrInsert(['key' => $key], ['category' => $category, 'value' => $value, 'updated_at' => now()]);
    }

    \App\Support\FormLabel::forgetOffered();

    return redirect()->route('settings.school')->with('success', 'School settings saved.');
}

/**
 * Display institution profile settings.
 */
public function institution()
{
    // Get institution settings from DB
    $settings = DB::table('settings')->where('category', 'institution')->get();
    
    return view('settings.institution', compact('settings'));
}

/**
 * Update institution profile settings.
 */
public function updateInstitution(Request $request)
{
    try {
        // Validate request
        $validator = Validator::make($request->all(), [
            'institution_name' => 'required|string|max:255',
            'institution_slogan' => 'nullable|string|max:255',
            'institution_address' => 'required|string',
            'institution_phone' => 'required|string|max:20',
            'institution_email' => 'required|email',
            'institution_website' => 'nullable|url',
            'institution_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'academic_affairs_signature' => 'nullable|image|mimes:jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.institution')
                ->withErrors($validator)
                ->withInput();
        }

        // Update settings in database
        $settings = [
            'institution_name',
            'institution_slogan',
            'institution_address',
            'institution_phone',
            'institution_email',
            'institution_website'
        ];

        foreach ($settings as $key) {
            if ($request->has($key)) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key, 'category' => 'institution'],
                    [
                        'value' => $request->input($key),
                        'type' => 'text'
                    ]
                );
            }
        }

        // Handle logo upload if present
        if ($request->hasFile('institution_logo')) {
            $logo = $request->file('institution_logo');
            // The public disk (storage/app/public) is what the layout, letterhead and login
            // screens read the logo from, and what /storage serves.
            $path = $logo->storeAs('logos', $logo->hashName(), 'public');
            
            // Update or insert logo path in settings
            DB::table('settings')->updateOrInsert(
                ['key' => 'institution_logo', 'category' => 'institution'],
                [
                    'value' => $path,
                    'type' => 'image'
                ]
            );
        }

        // Handle academic affairs signature upload if present
        if ($request->hasFile('academic_affairs_signature')) {
            $signature = $request->file('academic_affairs_signature');
            
            // Store in public disk under signatures directory
            $path = $signature->storeAs('signatures', $signature->hashName(), 'public');
            
            // Update or insert signature path in settings
            DB::table('settings')->updateOrInsert(
                ['key' => 'academic_affairs_signature', 'category' => 'institution'],
                [
                    'value' => $path,
                    'type' => 'image'
                ]
            );
        }

        return redirect()->route('settings.institution')
            ->with('success', 'Institution profile updated successfully.');
                
        } catch (\Exception $e) {
            return redirect()->route('settings.institution')
                ->with('error', 'Failed to update institution profile: ' . $e->getMessage());
        }
    }
    
    /**
     * Download a database backup.
     */
    public function downloadBackup(string $filename)
    {
        try {
            $backupPath = storage_path('app/backups');
            $filePath = $backupPath . '/' . $filename;
            
            // Security check: ensure the file is within the backups directory
            if (!str_starts_with(realpath($filePath), realpath($backupPath))) {
                return redirect()->route('settings.backup')
                    ->with('error', 'Invalid backup file path.');
            }
            
            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return redirect()->route('settings.backup')
                    ->with('error', 'Backup file not found or not readable.');
            }
            
            // Validate file extension
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                return redirect()->route('settings.backup')
                    ->with('error', 'Invalid backup file type.');
            }
            
            // Log debug information
            \Log::debug('Downloading backup', [
                'filename' => $filename,
                'path' => $filePath,
                'exists' => file_exists($filePath),
                'readable' => is_readable($filePath),
                'size' => filesize($filePath)
            ]);
            
            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/sql',
                'Content-Disposition' => 'attachment; filename=' . $filename,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Backup download failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('settings.backup')
                ->with('error', 'Failed to download backup: ' . $e->getMessage());
        }
    }

    /**
     * Delete a database backup.
     */
    public function destroyBackup(string $filename)
    {
        try {
            $backupPath = storage_path('app/backups');
            $filePath = $backupPath . '/' . $filename;
            
            // Security check: ensure the file is within the backups directory
            if (!str_starts_with(realpath($filePath), realpath($backupPath))) {
                return redirect()->route('settings.backup')
                    ->with('error', 'Invalid backup file path.');
            }
            
            // Validate file exists
            if (!file_exists($filePath)) {
                return redirect()->route('settings.backup')
                    ->with('error', 'Backup file not found.');
            }
            
            // Attempt to delete the file
            if (!unlink($filePath)) {
                throw new \Exception('Failed to delete file');
            }
            
            return redirect()->route('settings.backup')
                ->with('success', 'Backup deleted successfully.');
                
        } catch (\Exception $e) {
            \Log::error('Backup deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('settings.backup')
                ->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }

    /**
     * Set an academic year as current.
     */
    public function setCurrentAcademicYear(AcademicYear $academicYear)
    {
        try {
            DB::beginTransaction();
            
            // Unset all current academic years
            DB::statement('UPDATE academic_years SET is_current = 0');
            
            // Set this one as current
            DB::statement('UPDATE academic_years SET is_current = 1 WHERE id = ?', [$academicYear->id]);

            // The current term must belong to the current year: keep this year's current term if it
            // has one, otherwise start it on its first term.
            $terms = Semester::where('academic_year_id', $academicYear->id)->orderBy('semester_number')->get();
            $keep = $terms->firstWhere('is_current', true) ?? $terms->first();
            DB::statement('UPDATE semesters SET is_current = 0');
            if ($keep) {
                DB::statement('UPDATE semesters SET is_current = 1 WHERE id = ?', [$keep->id]);
            }
            
            DB::commit();
            
            return redirect()->route('settings.academic-years')
                ->with('success', 'Academic year set as current successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->route('settings.academic-years')
                ->with('error', 'Failed to set academic year as current: ' . $e->getMessage());
        }
    }
}

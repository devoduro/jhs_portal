<?php

namespace App\Http\Controllers;

use App\Exports\AdmissionTemplateExport;
use App\Imports\AdmissionImport;
use App\Models\AdmissionApplication;
use App\Models\Programme;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Admin side of the online admission module: import the placement list,
 * review completed applications, and confirm ("admit") them into the main
 * system - which creates the real Student record and login account.
 */
class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $programmes = Programme::orderBy('name')->get();

        $query = AdmissionApplication::with('programme')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('programme_id')) {
            $query->where('programme_id', $request->programme_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate(20)->withQueryString();

        $statusCounts = AdmissionApplication::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $feeAmount = Setting::get('admission_fee_amount', '50');

        return view('admissions.index', compact('applications', 'programmes', 'statusCounts', 'feeAmount'));
    }

    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admission_fee_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Setting::updateOrCreate(
            ['key' => 'admission_fee_amount'],
            ['category' => 'admission', 'value' => $request->admission_fee_amount]
        );

        return back()->with('success', 'Admission fee updated.');
    }

    public function importForm()
    {
        return view('admissions.import');
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admissions.import.form')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $import = new AdmissionImport();
            Excel::import($import, $request->file('excel_file'));

            $stats = $import->getStats();

            return redirect()->route('admissions.import.form')->with('importStats', $stats);
        } catch (\Exception $e) {
            return redirect()->route('admissions.import.form')
                ->with('error', 'Error importing placement list: '.$e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new AdmissionTemplateExport, 'admission_placement_template.xlsx');
    }

    /**
     * Bulk-remove placements nobody has touched yet (status still "placed" -
     * no payment attempt, no profile, nothing an applicant would lose). Lets
     * an admin cleanly recover from uploading the wrong file without hunting
     * down and deleting rows one at a time.
     */
    public function clearUnpaid(Request $request)
    {
        $count = AdmissionApplication::where('status', AdmissionApplication::STATUS_PLACED)->count();

        if ($count === 0) {
            return back()->with('error', 'There are no unpaid placements to clear.');
        }

        AdmissionApplication::where('status', AdmissionApplication::STATUS_PLACED)->delete();

        return back()->with('success', "Removed {$count} unpaid placement(s).");
    }

    public function show(AdmissionApplication $admission)
    {
        $admission->load(['programme', 'academicYear', 'reviewer', 'payments' => fn ($q) => $q->orderByDesc('created_at'), 'student']);

        return view('admissions.show', ['application' => $admission]);
    }

    public function admit(Request $request, AdmissionApplication $admission, PastechSmsService $sms)
    {
        if ($admission->status !== AdmissionApplication::STATUS_SUBMITTED) {
            return back()->with('error', 'Only submitted applications can be admitted.');
        }

        $missing = collect([
            'full name' => $admission->full_name,
            'date of birth' => $admission->date_of_birth,
            'gender' => $admission->gender,
            'programme' => $admission->programme_id,
            'boarding status' => $admission->boarding_status,
        ])->filter(fn ($value) => empty($value))->keys();

        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Cannot admit - missing: '.$missing->implode(', ').'.');
        }

        if (Student::where('index_number', $admission->index_number)->exists()) {
            return back()->with('error', 'A student with this index number already exists in the system.');
        }

        $relationship = $admission->guardian_name ? 'Guardian' : ($admission->father_name ? 'Father' : ($admission->mother_name ? 'Mother' : null));
        $guardianName = $admission->guardian_name ?: $admission->father_name ?: $admission->mother_name;

        DB::transaction(function () use ($admission, $relationship, $guardianName) {
            $student = Student::create([
                'index_number' => $admission->index_number,
                'full_name' => $admission->full_name,
                'date_of_birth' => $admission->date_of_birth,
                'gender' => $admission->gender,
                'programme_id' => $admission->programme_id,
                'level' => 100,
                'boarding_status' => $admission->boarding_status,
                'status' => 'active',
                'profile_photo' => $admission->photo_path,
                'emergency_contact_name' => $guardianName,
                'emergency_contact_phone' => $admission->phone,
                'emergency_contact_relationship' => $relationship,
                'email' => $admission->email,
                'phone' => $admission->phone,
                'address' => $admission->address,
                'hometown' => $admission->town,
                'gps_address' => $admission->digital_address,
            ]);

            // Reuse the exact password the applicant already chose (see
            // Auth\ApplicantAuthController::setPassword) so their portal login
            // carries straight over - no separate "first login" reset needed.
            User::create([
                'name' => $student->full_name,
                'email' => $admission->email ?: $student->index_number.'@student.local',
                'password' => $admission->password,
                'role' => User::ROLE_STUDENT,
                'index_number' => $student->index_number,
                'student_id' => $student->id,
                'first_login' => false,
            ]);

            $admission->update([
                'status' => AdmissionApplication::STATUS_ADMITTED,
                'student_id' => $student->id,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        });

        if ($admission->phone) {
            try {
                $institutionName = Setting::get('institution_name', 'the school');
                $message = "Congratulations! {$admission->full_name} has been admitted to {$institutionName}. Index Number: {$admission->index_number}. Log in to the admission portal to print your admission slip.";
                $sms->sendSms($admission->phone, $message);
            } catch (\Throwable $e) {
                Log::warning("Admission confirmation SMS failed for application {$admission->id}: ".$e->getMessage());
            }
        }

        return redirect()->route('admissions.show', $admission)->with('success', 'Applicant admitted - a Student record and login account have been created.');
    }

    public function reject(Request $request, AdmissionApplication $admission)
    {
        if (!in_array($admission->status, [AdmissionApplication::STATUS_PAID, AdmissionApplication::STATUS_SUBMITTED], true)) {
            return back()->with('error', 'This application cannot be rejected from its current status.');
        }

        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $admission->update([
            'status' => AdmissionApplication::STATUS_REJECTED,
            'review_notes' => $request->review_notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admissions.show', $admission)->with('success', 'Application marked as rejected.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Registration;
use App\Models\Semester;
use App\Services\FeeLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationController extends Controller
{
    /**
     * Show the student's registration status and currently registered subjects.
     */
    public function index()
    {
        $student = Auth::user()->student;
        $currentSemester = Semester::with('academicYear')->where('is_current', true)->first();

        $registrations = collect();
        $meetsThreshold = false;
        $percentage = 0;
        $balance = 0;
        $feeStructure = null;
        $isBiometricVerified = false;
        $totalArrears = $student->totalArrears();
        $ledger = FeeLedgerService::ledgerFor($student);
        $balanceDue = $ledger[0]['balance'] ?? 0.0;
        $maxAllowedBalance = 0;

        if ($currentSemester) {
            $registrations = $student->registrations()
                ->with('subject')
                ->where('semester_id', $currentSemester->id)
                ->where('status', 'registered')
                ->get();

            $academicYear = $currentSemester->academicYear;
            $feeStructure = $student->applicableFeeStructure($academicYear);
            $percentage = $student->paymentPercentage($academicYear);
            $balance = $student->feeBalance($academicYear);
            $meetsThreshold = $student->meetsRegistrationThreshold($currentSemester);
            $isBiometricVerified = $student->hasBiometricVerification($currentSemester);

            // The exact quantity meetsRegistrationThreshold() gates on, so the "not met"
            // message below can cite real numbers instead of the un-netted tuition percentage.
            $billAmount = $student->tuitionFeeAmount($academicYear);
            $required = (float) ($currentSemester->required_payment_percentage ?? 0);
            $maxAllowedBalance = $billAmount - ($billAmount * $required / 100);
        }

        return view('student.registration.index', compact(
            'student',
            'currentSemester',
            'registrations',
            'meetsThreshold',
            'percentage',
            'balance',
            'feeStructure',
            'isBiometricVerified',
            'totalArrears',
            'ledger',
            'balanceDue',
            'maxAllowedBalance'
        ));
    }

    /**
     * Show the subject selection form for the current semester.
     */
    public function create()
    {
        $student = Auth::user()->student;
        $currentSemester = Semester::with('academicYear')->where('is_current', true)->first();

        if (!$this->canRegister($student, $currentSemester)) {
            return redirect()->route('student.registration.index')
                ->with('error', 'Subject registration is not currently available to you.');
        }

        $availableSubjects = $this->availableSubjects($student, $currentSemester);

        $registeredSubjectIds = $student->registrations()
            ->where('semester_id', $currentSemester->id)
            ->where('status', 'registered')
            ->pluck('subject_id')
            ->toArray();

        return view('student.registration.create', compact('currentSemester', 'availableSubjects', 'registeredSubjectIds'));
    }

    /**
     * Sync the student's registered subjects for the current semester to match the submitted
     * selection - registers newly-checked subjects and drops previously-registered subjects that
     * were unchecked, so the same form can be used to both register and edit a registration.
     */
    public function store(Request $request)
    {
        $student = Auth::user()->student;
        $currentSemester = Semester::with('academicYear')->where('is_current', true)->first();

        if (!$this->canRegister($student, $currentSemester)) {
            return redirect()->route('student.registration.index')
                ->with('error', 'Subject registration is not currently available to you.');
        }

        $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'integer',
        ]);

        $allowedSubjectIds = $this->availableSubjects($student, $currentSemester)->pluck('id')->toArray();
        $selectedSubjectIds = array_intersect($request->subject_ids, $allowedSubjectIds);

        if (empty($selectedSubjectIds)) {
            return redirect()->route('student.registration.create')
                ->with('error', 'None of the selected subjects are available for registration.');
        }

        $currentRegistrations = $student->registrations()
            ->where('semester_id', $currentSemester->id)
            ->where('status', 'registered')
            ->get();
        $currentSubjectIds = $currentRegistrations->pluck('subject_id')->toArray();

        $toAdd = array_diff($selectedSubjectIds, $currentSubjectIds);
        $toRemove = array_diff($currentSubjectIds, $selectedSubjectIds);

        foreach ($toAdd as $subjectId) {
            Registration::firstOrCreate([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'semester_id' => $currentSemester->id,
                'academic_year_id' => $currentSemester->academic_year_id,
            ], [
                'status' => 'registered',
            ]);
        }

        if (!empty($toRemove)) {
            $currentRegistrations->whereIn('subject_id', $toRemove)->each->delete();
        }

        return redirect()->route('student.registration.index')
            ->with('success', 'Subject registration updated successfully.');
    }

    /**
     * Print the student's already-submitted registration slip for the current semester.
     * Only allowed once subjects have actually been registered - there is nothing to print
     * before submission.
     */
    public function print()
    {
        $student = Auth::user()->student;
        $currentSemester = Semester::with('academicYear')->where('is_current', true)->first();

        $subjects = collect();
        if ($currentSemester) {
            $subjects = Subject::whereIn('id', $student->registrations()
                ->where('semester_id', $currentSemester->id)
                ->where('status', 'registered')
                ->pluck('subject_id'))
                ->orderBy('code')
                ->get();
        }

        if ($subjects->isEmpty()) {
            return redirect()->route('student.registration.index')
                ->with('error', 'You have not registered any subjects yet, so there is nothing to print.');
        }

        $settings = \Illuminate\Support\Facades\DB::table('settings')->where('category', 'institution')->pluck('value', 'key')->toArray();

        return view('student.registration.print', [
            'student' => $student,
            'semester' => $currentSemester,
            'subjects' => $subjects,
            'settings' => $settings,
        ]);
    }

    /**
     * Drop a registered subject while registration is still open.
     */
    public function destroy(Registration $registration)
    {
        $student = Auth::user()->student;

        if ($registration->student_id !== $student->id) {
            abort(403);
        }

        $semester = $registration->semester;

        if (!$semester || !$semester->isRegistrationOpen()) {
            return redirect()->route('student.registration.index')
                ->with('error', 'Registration is closed for this semester, so subjects cannot be dropped.');
        }

        $registration->delete();

        return redirect()->route('student.registration.index')
            ->with('success', 'Subject dropped successfully.');
    }

    /**
     * Determine whether the student is currently allowed to register subjects.
     */
    protected function canRegister($student, ?Semester $semester): bool
    {
        if (!$student || !$semester || !$semester->isRegistrationOpen()) {
            return false;
        }

        return $student->hasBiometricVerification($semester) && $student->meetsRegistrationThreshold($semester);
    }

    /**
     * Get the subjects available to a student for a given semester (their programme's subjects in that semester).
     */
    protected function availableSubjects($student, ?Semester $semester)
    {
        if (!$semester) {
            return collect();
        }

        return Subject::where('semester_id', $semester->id)
            ->where('level', $student->level)
            ->whereHas('programmes', function ($query) use ($student) {
                $query->where('programmes.id', $student->programme_id);
            })
            ->orderBy('code')
            ->get();
    }
}

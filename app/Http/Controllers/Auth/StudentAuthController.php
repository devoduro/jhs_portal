<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TimetableController;
use App\Models\Assignment;
use App\Models\CaScoreSetting;
use App\Models\ContinuousAssessment;
use App\Models\Quiz;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\ActivityLogger;

class StudentAuthController extends Controller
{
    /**
     * Show the student login form.
     */
    public function showLoginForm()
    {
        return view('auth.student-login');
    }

    /**
     * Show the student change password form.
     */
    public function showChangePasswordForm()
    {
        if (auth()->user()->role !== User::ROLE_STUDENT) {
            return redirect()->route('login')->with('error', 'Access denied. Students only.');
        }
        return view('student.student_change-password');
    }

    /**
     * Handle student login request.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('student.login')
                ->withErrors($validator)
                ->withInput();
        }

        // Try to find student by index_number
        $student = Student::where('index_number', $request->index_number)->first();
        
        if (!$student) {
            return redirect()->route('student.login')
                ->withErrors(['index_number' => 'Invalid credentials'])
                ->withInput();
        }
        
        // Find user linked to this student
        $user = User::where('student_id', $student->id)->first();
        
        if (!$user) {
            return redirect()->route('student.login')
                ->withErrors(['index_number' => 'No user account found for this student'])
                ->withInput();
        }
        
        // Verify that the user has the student role
        if ($user->role !== User::ROLE_STUDENT) {
            return redirect()->route('student.login')
                ->withErrors(['index_number' => 'Invalid student account'])
                ->withInput();
        }

        // Attempt login with user credentials
        if (Auth::attempt(['email' => $user->email, 'password' => $request->password, 'role' => User::ROLE_STUDENT])) {
            $request->session()->regenerate();
            
            // Log the successful login
            app(ActivityLogger::class)->log('login', 'Student logged in successfully');
            
            // Set last activity timestamp
            $request->session()->put('last_activity', time());
            
            // If first login, redirect to password change
            if ($user->first_login) {
                return redirect()->route('student.change-password')
                    ->with('warning', 'Please change your password before continuing.');
            }
            
            // Otherwise redirect to dashboard
            return redirect()->route('student.dashboard');
        }

        return redirect()->route('student.login')
            ->withErrors(['index_number' => 'Invalid credentials'])
            ->withInput();
    }

    /**
     * Log the student out.
     */
    public function logout(Request $request)
    {
        // Log the logout activity before actually logging out
        if (Auth::check()) {
            app(ActivityLogger::class)->log('logout', 'Student logged out');
        }
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('student.login')->with('message', 'You have been logged out successfully.');
    }

    /**
     * Show the student dashboard.
     */
    public function dashboard()
    {
        $student = Auth::user()->student;
        $results = $student->results()
            ->with(['subject', 'semester', 'academicYear'])
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('semester_id', 'desc')
            ->get();

        // Calculate WASSCE aggregate and its change from the semester immediately before
        // the most recent one with results - found by actual calendar date, not by
        // assuming semester_id - 1 is "the previous semester" (semester ids are just
        // insertion-order primary keys, not a chronological guarantee).
        $aggregate = $student->calculateAggregate();
        $aggregateChange = 0;
        $latestSemester = $results->first()?->semester;

        if ($latestSemester) {
            $previousSemester = Semester::where('start_date', '<', $latestSemester->start_date)
                ->orderByDesc('start_date')
                ->first();

            if ($previousSemester) {
                $previousAggregate = $student->calculateAggregate($previousSemester->id);
                if ($previousAggregate !== null) {
                    $aggregateChange = $aggregate - $previousAggregate;
                }
            }
        }

        // Subjects actually offered to this student right now (same enrollment logic as
        // Subject::students() - active, at their form, on their programme), not just
        // subjects that happen to have a result recorded yet.
        $currentSubjectIds = Subject::where('level', $student->level)->pluck('id');
        $balance = $student->outstandingBalance();

        // Summary of the most recent term the pupil has scores in (position, average).
        $latestReport = null;
        if ($latestSemester) {
            $classGroup = \App\Services\TerminalReportService::classFor($student, $latestSemester);
            if ($classGroup) {
                $data = \App\Services\TerminalReportService::forClass($classGroup, $latestSemester, [$student->id]);
                $mine = $data['pupils']->first();
                $latestReport = $mine ? [
                    'term' => $latestSemester->loadMissing('academicYear'),
                    'position' => $mine['position'],
                    'roll' => $data['roll'],
                    'average' => $mine['average'],
                    'aggregate' => $mine['aggregate'],
                ] : null;
            }
        }
        $isJhs = \App\Support\FormLabel::isJhs($student->level);

        // Get WASSCE division standing and other data
        $gradeStanding = $student->getGradeStanding();

        // Today's classes for the dashboard timetable widget, based on the student's class group
        $currentSemester = Semester::where('is_current', true)->first();
        $todayEntries = collect();
        if ($currentSemester && $student->class_group_id) {
            $todayEntries = TimetableController::fetchEntries([
                'class_group_id' => $student->class_group_id,
                'semester_id' => $currentSemester->id,
            ])->where('day_of_week', now()->dayOfWeek)->sortBy('start_time')->values();
        }

        // Pending work - quizzes not yet completed and assignments not yet submitted,
        // for a quick "what do I still owe" glance (see Quiz/Assignment eligibility
        // logic in StudentQuizController/StudentAssignmentController for the full rules
        // this approximates).
        $pendingQuizzesCount = Quiz::whereIn('subject_id', $currentSubjectIds)
            ->where('is_active', true)
            ->where(function ($q) use ($student) {
                $q->whereNull('class_group_id')->orWhere('class_group_id', $student->class_group_id);
            })
            ->whereDoesntHave('attempts', function ($q) use ($student) {
                $q->where('student_id', $student->id)->where('status', 'completed');
            })
            ->count();

        $pendingAssignmentsCount = Assignment::whereHas('classGroups', fn ($q) => $q->where('class_groups.id', $student->class_group_id))
            ->where('is_active', true)
            ->where('due_at', '>', now())
            ->whereDoesntHave('submissions', function ($q) use ($student) {
                $q->where('student_id', $student->id)->whereNotNull('submitted_at');
            })
            ->count();

        return view('student.dashboard', compact(
            'student',
            'results',
            'aggregate',
            'aggregateChange',
            'gradeStanding',
            'currentSubjectIds',
            'currentSemester',
            'todayEntries',
            'pendingQuizzesCount',
            'pendingAssignmentsCount',
            'balance',
            'isJhs',
            'latestReport'
        ));
    }

    /**
     * Show the student's own weekly timetable for their class group.
     */
    public function timetable(Request $request)
    {
        $student = Auth::user()->student;

        $semesters = Semester::orderBy('academic_year_id', 'desc')->orderBy('semester_number')->get();
        $semester = $request->filled('semester_id')
            ? $semesters->firstWhere('id', (int) $request->semester_id)
            : $semesters->firstWhere('is_current', true);

        $entries = collect();
        $slotLabels = [];
        $classSummary = null;

        if ($semester && $student->class_group_id) {
            $entries = TimetableController::fetchEntries(['class_group_id' => $student->class_group_id, 'semester_id' => $semester->id]);
            TimetableController::applyGridPositions($entries);
            $slotLabels = TimetableController::gridSlotLabels();
            $classSummary = TimetableController::classSummary($student->class_group_id, $semester->id);
        }

        return view('student.timetable', compact('student', 'semesters', 'semester', 'entries', 'slotLabels', 'classSummary'));
    }

    /**
     * Print the student's own timetable for their class group.
     */
    public function printTimetable(Request $request)
    {
        $student = Auth::user()->student;

        abort_unless($student->class_group_id, 404, 'You have not been assigned to a class yet.');

        $semesterId = $request->filled('semester_id')
            ? (int) $request->semester_id
            : optional(Semester::where('is_current', true)->first())->id;

        abort_unless($semesterId, 404, 'No semester is available to print.');

        return TimetableController::buildPrintView(['class_group_id' => $student->class_group_id, 'semester_id' => $semesterId], $student);
    }

    /**
     * Show the profile edit form.
     */
    public function editProfile()
    {
        $user = Auth::user();
        $student = $user->student;
        return view('student.student-profile-edit', compact('user', 'student'));
    }

    /**
     * Update the student's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $student = $user->student;

        // If only updating profile photo
        if ($request->hasFile('profile_photo')) {
            $request->validate([
                'profile_photo' => ['required', 'image', 'max:2048'],
            ]);

            // Delete old photo if it exists
            if ($student->profile_photo) {
                Storage::disk('public')->delete($student->profile_photo);
            }

            // Store new photo
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $student->update(['profile_photo' => $path]);

            return redirect()->route('student.profile.edit')
                ->with('success', 'Profile photo updated successfully.');
        }

        // If updating other profile information
        $request->validate([
            // Young pupils rarely have their own email or phone - only the parent's number is needed.
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('students')->ignore($student->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'hometown' => ['nullable', 'string', 'max:255'],
            'gps_address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);

        $student->update([
            'email' => $request->email,
            'phone' => \App\Support\Phone::clean($request->phone),
            'address' => $request->address,
            // The date of birth is the pupil's starting password and part of the school record, so it
            // can be filled in when missing but not changed from here (the office edits it).
            'date_of_birth' => $student->date_of_birth ?? $request->date_of_birth,
            'hometown' => $request->hometown,
            'gps_address' => $request->gps_address,
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => \App\Support\Phone::clean($request->emergency_contact_phone),
            'emergency_contact_relationship' => $request->emergency_contact_relationship,
        ]);

        return redirect()->route('student.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Remove the student's profile photo.
     */
    public function removeProfilePhoto()
    {
        $user = Auth::user();
        $student = $user->student;

        if ($student->profile_photo) {
            Storage::disk('public')->delete($student->profile_photo);
            $student->update(['profile_photo' => null]);
        }

        return redirect()->route('student.profile.edit')
            ->with('success', 'Profile photo removed successfully.');
    }

    /**
     * Update the student's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        
        // Different validation rules for first login vs regular password change
        if ($user->first_login) {
            $request->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
        } else {
            $request->validate([
                'current_password' => ['required', 'string'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            // Verify current password for non-first-login changes
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.']);
            }
        }

        // Update password and first_login flag
        $user->password = Hash::make($request->password);
        $user->first_login = false;
        $user->save();

        // Redirect to intended URL or dashboard
        if ($request->session()->has('url.intended')) {
            $redirect = redirect()->intended(route('student.dashboard'));
        } else {
            $redirect = redirect()->route('student.dashboard');
        }

        return $redirect->with('success', 'Password updated successfully.');
    }
}

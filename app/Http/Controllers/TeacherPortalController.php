<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Student;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeacherPortalController extends Controller
{
    /**
     * Show the authenticated teacher's own profile edit form.
     */
    public function profile()
    {
        $teacher = $this->authTeacherOrAbort();

        return view('teachers.my-profile', compact('teacher'));
    }

    /**
     * Update the authenticated teacher's own contact details/photo.
     * Name, staff ID and department stay admin-managed (edited via teachers.edit).
     */
    public function updateProfile(Request $request)
    {
        $teacher = $this->authTeacherOrAbort();

        $validator = Validator::make($request->all(), [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher->id)],
            'phone' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->route('teacher.profile.edit')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['email', 'phone']);

        if ($request->hasFile('profile_photo')) {
            if ($teacher->profile_photo) {
                Storage::disk('public')->delete($teacher->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')->store('lecturer-photos', 'public');
        }

        $teacher->update($data);

        return redirect()->route('teacher.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Show the authenticated teacher's own weekly timetable.
     */
    public function timetable(Request $request)
    {
        $teacher = $this->authTeacherOrAbort();

        $semesters = Semester::orderBy('academic_year_id', 'desc')->orderBy('semester_number')->get();
        $semester = $request->filled('semester_id')
            ? $semesters->firstWhere('id', (int) $request->semester_id)
            : $semesters->firstWhere('is_current', true);

        $entries = collect();
        $slotLabels = [];
        $workload = ['classes' => 0, 'workload' => 0.0];

        if ($semester) {
            $entries = TimetableController::fetchEntries(['teacher_id' => $teacher->id, 'semester_id' => $semester->id]);
            TimetableController::applyGridPositions($entries);
            $slotLabels = TimetableController::gridSlotLabels();
            $workload = TimetableController::calculateWorkload($teacher->id, $semester->id);
        }

        return view('teachers.my-timetable', compact('teacher', 'semesters', 'semester', 'entries', 'slotLabels', 'workload'));
    }

    /**
     * Print the authenticated teacher's own timetable. Not gated by the admin-only
     * manage-timetable permission since this only ever shows the teacher their own schedule.
     */
    public function printTimetable(Request $request)
    {
        $teacher = $this->authTeacherOrAbort();

        $semesterId = $request->filled('semester_id')
            ? (int) $request->semester_id
            : optional(Semester::where('is_current', true)->first())->id;

        abort_unless($semesterId, 404, 'No semester is available to print.');

        return TimetableController::buildPrintView(['teacher_id' => $teacher->id, 'semester_id' => $semesterId]);
    }

    /**
     * Resolve the teacher profile linked to the authenticated user, or deny access.
     */
    protected function authTeacherOrAbort()
    {
        $teacher = Auth::user()->teacher;

        abort_unless($teacher, 403, 'This area is only available to teacher accounts.');

        return $teacher;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Exeat;
use App\Models\House;
use App\Models\Semester;
use App\Models\Student;
use App\Services\CampusAttendanceReportService;
use App\Services\ExeatService;
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
     * Show the authenticated teacher's house(s) and the students assigned to each,
     * for teachers who have been assigned as a house master.
     */
    public function myHouses()
    {
        $teacher = $this->authTeacherOrAbort();

        $houses = House::where('house_master_id', $teacher->id)
            ->with(['students' => function ($query) {
                $query->with(['programme', 'classGroup'])->orderBy('full_name');
            }])
            ->orderBy('name')
            ->get();

        abort_if($houses->isEmpty(), 403, 'You have not been assigned as a house master for any house.');

        return view('teachers.my-houses', compact('teacher', 'houses'));
    }

    /**
     * List exeats for boarders in the houses the authenticated teacher manages.
     */
    public function houseExeats(Request $request)
    {
        $teacher = $this->authTeacherOrAbort();
        $houses = $this->managedHousesOrAbort($teacher);

        $query = Exeat::whereHas('student', fn ($q) => $q->whereIn('house_id', $houses->pluck('id')))
            ->with(['student.house', 'issuedBy'])
            ->orderByDesc('start_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $today = Carbon::today()->toDateString();

            match ($request->status) {
                'upcoming' => $query->whereDate('start_date', '>', $today),
                'active' => $query->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today),
                'completed' => $query->whereDate('end_date', '<', $today),
                default => null,
            };
        }

        if ($request->filled('from')) {
            $query->whereDate('end_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('start_date', '<=', $request->to);
        }

        $exeats = $query->paginate(20)->withQueryString();

        return view('teachers.my-house-exeats', compact('teacher', 'houses', 'exeats'));
    }

    /**
     * Show the form for granting a new exeat to a boarder in the teacher's house(s).
     */
    public function createHouseExeat()
    {
        $teacher = $this->authTeacherOrAbort();
        $houses = $this->managedHousesOrAbort($teacher);

        $students = Student::whereIn('house_id', $houses->pluck('id'))
            ->where('boarding_status', 'boarder')
            ->with('house')
            ->orderBy('full_name')
            ->get();

        return view('teachers.my-house-exeats-create', compact('teacher', 'houses', 'students'));
    }

    /**
     * Store a new exeat for a boarder in the teacher's house(s) and text their emergency contact.
     */
    public function storeHouseExeat(Request $request, PastechSmsService $sms)
    {
        $teacher = $this->authTeacherOrAbort();
        $houses = $this->managedHousesOrAbort($teacher);

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'reason' => 'required|string|max:1000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->route('teacher.house-exeats.create')
                ->withErrors($validator)
                ->withInput();
        }

        $student = Student::findOrFail($request->student_id);

        if (!in_array($student->house_id, $houses->pluck('id')->all(), true)) {
            return redirect()->route('teacher.house-exeats.create')
                ->with('error', 'That student is not in a house you manage.')
                ->withInput();
        }

        if (!$student->isBoarder()) {
            return redirect()->route('teacher.house-exeats.create')
                ->with('error', 'Exeat can only be granted to boarding students.')
                ->withInput();
        }

        ExeatService::create(
            $student,
            $request->reason,
            $request->start_date,
            $request->end_date,
            Auth::id(),
            $sms
        );

        return redirect()->route('teacher.house-exeats')
            ->with('success', "Exeat granted to {$student->full_name}. Their emergency contact has been notified by SMS.");
    }

    /**
     * Show the campus gate attendance register for the houses the authenticated
     * teacher manages as house master, over a date range (defaults to today).
     */
    public function houseAttendance(Request $request)
    {
        [$teacher, $houses, $students, $from, $to] = $this->resolveHouseAttendanceScope($request);

        $report = $this->filteredAttendanceReport($students, $from, $to, $request->input('status'));

        return view('teachers.my-house-attendance', compact('teacher', 'houses', 'report', 'from', 'to'));
    }

    /**
     * Printable version of the house master's own attendance register.
     */
    public function printHouseAttendance(Request $request)
    {
        [$teacher, $houses, $students, $from, $to] = $this->resolveHouseAttendanceScope($request);

        $report = $this->filteredAttendanceReport($students, $from, $to, $request->input('status'));
        $house = $houses->count() === 1 ? $houses->first() : null;

        return view('biometric.campus-attendance.print', compact('report', 'from', 'to', 'house'));
    }

    /**
     * Resolve the authenticated teacher's managed houses, their students (optionally
     * narrowed by a name/index search), and the requested date range (defaulting to
     * today) - shared by the on-screen and print actions above.
     *
     * @return array{0: \App\Models\Teacher, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: Carbon, 4: Carbon}
     */
    protected function resolveHouseAttendanceScope(Request $request): array
    {
        $teacher = $this->authTeacherOrAbort();

        $houses = House::where('house_master_id', $teacher->id)->orderBy('name')->get();

        abort_if($houses->isEmpty(), 403, 'You have not been assigned as a house master for any house.');

        $studentsQuery = \App\Models\Student::whereIn('house_id', $houses->pluck('id'))
            ->with('house')
            ->orderBy('full_name');

        if ($request->filled('search')) {
            $search = $request->search;
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        $students = $studentsQuery->get();

        $from = $request->filled('from') ? Carbon::parse($request->from) : Carbon::today();
        $to = $request->filled('to') ? Carbon::parse($request->to) : Carbon::today();

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        return [$teacher, $houses, $students, $from, $to];
    }

    /**
     * Build the day-by-day register, then optionally drop rows that don't match
     * the requested status ("On Campus" / "Off Campus" / "No Record").
     */
    protected function filteredAttendanceReport($students, Carbon $from, Carbon $to, ?string $status): array
    {
        $report = CampusAttendanceReportService::build($students, $from, $to);

        $statusLabels = [
            'on_campus' => 'On Campus',
            'off_campus' => 'Off Campus',
            'no_record' => 'No Record',
        ];

        $wanted = $status ? ($statusLabels[$status] ?? null) : null;

        if (!$wanted) {
            return $report;
        }

        return array_map(
            fn (array $rows) => array_values(array_filter($rows, fn (array $row) => $row['status'] === $wanted)),
            $report
        );
    }

    /**
     * Resolve the houses the given teacher manages as house master, or deny
     * access if they aren't a house master for any house.
     */
    protected function managedHousesOrAbort(\App\Models\Teacher $teacher)
    {
        $houses = House::where('house_master_id', $teacher->id)->orderBy('name')->get();

        abort_if($houses->isEmpty(), 403, 'You have not been assigned as a house master for any house.');

        return $houses;
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

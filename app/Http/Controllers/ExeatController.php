<?php

namespace App\Http\Controllers;

use App\Models\Exeat;
use App\Models\House;
use App\Models\Student;
use App\Services\ExeatService;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExeatController extends Controller
{
    /**
     * Display a listing of all exeats across every house, filterable by house,
     * student, status (upcoming/active/completed), and date range.
     */
    public function index(Request $request)
    {
        $houses = House::orderBy('name')->get();

        $query = Exeat::with(['student.house', 'issuedBy'])->orderByDesc('start_date');

        if ($request->filled('house_id')) {
            $query->whereHas('student', fn ($q) => $q->where('house_id', $request->house_id));
        }

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

        // Overlap filter: an exeat matches [from, to] if any part of its own
        // date range falls within the requested window.
        if ($request->filled('from')) {
            $query->whereDate('end_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('start_date', '<=', $request->to);
        }

        $exeats = $query->paginate(20)->withQueryString();

        return view('exeats.index', compact('exeats', 'houses'));
    }

    /**
     * Show the form for granting a new exeat. Only boarders are eligible -
     * day students already leave campus daily and don't need one.
     */
    public function create(Request $request)
    {
        $houses = House::orderBy('name')->get();

        $studentsQuery = Student::where('boarding_status', 'boarder')->with('house')->orderBy('full_name');

        if ($request->filled('house_id')) {
            $studentsQuery->where('house_id', $request->house_id);
        }

        $students = $studentsQuery->get();

        return view('exeats.create', compact('students', 'houses'));
    }

    /**
     * Store a newly granted exeat and text the student's emergency contact.
     */
    public function store(Request $request, PastechSmsService $sms)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'reason' => 'required|string|max:1000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->route('exeats.create')
                ->withErrors($validator)
                ->withInput();
        }

        $student = Student::findOrFail($request->student_id);

        if (!$student->isBoarder()) {
            return redirect()->route('exeats.create')
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

        return redirect()->route('exeats.index')
            ->with('success', "Exeat granted to {$student->full_name}. Their emergency contact has been notified by SMS.");
    }

    /**
     * Remove a mistakenly granted exeat.
     */
    public function destroy(Exeat $exeat)
    {
        $exeat->delete();

        return redirect()->route('exeats.index')
            ->with('success', 'Exeat record deleted.');
    }
}

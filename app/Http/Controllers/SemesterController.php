<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Result;
use App\Models\Semester;
use App\Services\GradeNotificationService;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SemesterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $semesters = Semester::with('academicYear')
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('semester_number', 'asc')
            ->paginate(20);
            
        return view('semesters.index', compact('semesters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('semesters.create', compact('academicYears'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_number' => 'required|integer|min:1|max:3',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'vacation_date' => 'nullable|date',
            'next_term_begins' => 'nullable|date',
            'attendance_days' => 'nullable|integer|min:0|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->route('semesters.create')
                ->withErrors($validator)
                ->withInput();
        }

        // Check if semester with same semester_number already exists for this academic year
        $existingSemester = Semester::where('academic_year_id', $request->input('academic_year_id'))
            ->where('semester_number', $request->input('semester_number'))
            ->first();

        if ($existingSemester) {
            return redirect()->route('semesters.create')
                ->withErrors(['semester_number' => 'That term already exists for the selected academic year.'])
                ->withInput();
        }

        $data = $request->only(['name', 'academic_year_id', 'semester_number', 'start_date', 'end_date', 'vacation_date', 'next_term_begins', 'attendance_days']);
        foreach (['vacation_date', 'next_term_begins', 'attendance_days'] as $field) {
            if (($data[$field] ?? '') === '') {
                $data[$field] = null;
            }
        }

        Semester::create($data);

        return redirect()->route('semesters.index')
            ->with('success', 'Term created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return redirect()->route('semesters.edit', $id);
    }

    /**
     * Text every student with at least one result recorded this semester a summary
     * of their grades and a link to their full report card.
     */
    public function notifyParentsOfResults(Semester $semester, PastechSmsService $sms)
    {
        $studentIds = Result::where('semester_id', $semester->id)->distinct()->pluck('student_id');
        $students = Student::whereIn('id', $studentIds)->get();

        $sent = 0;
        $skipped = 0;

        foreach ($students as $student) {
            $smsLogId = GradeNotificationService::sendToParent($student, $semester, $sms);
            $smsLogId ? $sent++ : $skipped++;
        }

        $message = "Terminal report links texted to {$sent} parent(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} pupil(s) skipped (no parent phone number on file).";
        }

        return redirect()->route('semesters.index')->with('success', $message);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $semester = Semester::findOrFail($id);
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        
        return view('semesters.edit', compact('semester', 'academicYears'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $semester = Semester::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_number' => 'required|integer|min:1|max:3',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'vacation_date' => 'nullable|date',
            'next_term_begins' => 'nullable|date',
            'attendance_days' => 'nullable|integer|min:0|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->route('semesters.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        // Check if semester with same semester_number already exists for this academic year (excluding this one)
        $existingSemester = Semester::where('academic_year_id', $request->input('academic_year_id'))
            ->where('semester_number', $request->input('semester_number'))
            ->where('id', '!=', $id)
            ->first();

        if ($existingSemester) {
            return redirect()->route('semesters.edit', $id)
                ->withErrors(['semester_number' => 'That term already exists for the selected academic year.'])
                ->withInput();
        }

        $data = $request->only(['name', 'academic_year_id', 'semester_number', 'start_date', 'end_date', 'vacation_date', 'next_term_begins', 'attendance_days']);
        foreach (['vacation_date', 'next_term_begins', 'attendance_days'] as $field) {
            if (($data[$field] ?? '') === '') {
                $data[$field] = null;
            }
        }

        $semester->update($data);

        return redirect()->route('semesters.index')
            ->with('success', 'Term updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $semester = Semester::findOrFail($id);

        if ($semester->is_current) {
            return redirect()->route('semesters.index')
                ->with('error', 'This is the current term. Make another term current before deleting it.');
        }

        // A term with scores, bills, payments or report remarks is part of the school's records.
        if ($semester->results()->exists()) {
            return redirect()->route('semesters.index')
                ->with('error', 'Cannot delete a term that has scores recorded.');
        }

        if (\App\Models\StudentFeeCharge::where('semester_id', $semester->id)->exists() || \App\Models\StudentPayment::where('semester_id', $semester->id)->exists()) {
            return redirect()->route('semesters.index')
                ->with('error', 'Cannot delete a term that has fees billed or payments received.');
        }

        if ($semester->termReports()->exists()) {
            return redirect()->route('semesters.index')
                ->with('error', 'Cannot delete a term that has attendance and remarks entered.');
        }

        $semester->delete();

        return redirect()->route('semesters.index')
            ->with('success', 'Term deleted.');
    }
    
    /**
     * Filter semesters by academic year.
     */
    public function filterByAcademicYear(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');
        
        $semesters = Semester::with('academicYear')
            ->where('academic_year_id', $academicYearId)
            ->orderBy('semester_number', 'asc')
            ->paginate(20);
            
        $academicYear = AcademicYear::findOrFail($academicYearId);
            
        return view('semesters.index', compact('semesters', 'academicYear'));
    }
    
    /**
     * Set current semester.
     */
    public function setCurrent(Semester $semester)
    {
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Unset current semester flag for all semesters
            DB::statement('UPDATE semesters SET is_current = 0');
            
            // Set current semester
            DB::statement('UPDATE semesters SET is_current = 1 WHERE id = ?', [$semester->id]);
            
            // Set current academic year
            $academicYear = AcademicYear::findOrFail($semester->academic_year_id);
            DB::statement('UPDATE academic_years SET is_current = 0');
            DB::statement('UPDATE academic_years SET is_current = 1 WHERE id = ?', [$academicYear->id]);
            
            DB::commit();
            
            return redirect()->route('semesters.index')
                ->with('success', "{$semester->name} ({$academicYear->name}) is now the current term.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->route('semesters.index')
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
    
    /**
     * List results for a semester.
     */
    public function results(string $id)
    {
        $semester = Semester::with('academicYear')->findOrFail($id);
        
        $results = Result::where('semester_id', $id)
            ->with(['student', 'subject'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('semesters.results', compact('semester', 'results'));
    }
}

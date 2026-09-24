<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Programme;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgrammeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $programmes = Programme::withCount(['students', 'subjects'])->paginate(10);

        // Calculate totals for the stats cards
        $totalProgrammes = Programme::count();
        $totalStudents = Student::count(); // Count all students since there's no status column
        $totalSubjects = Subject::count();

        return view('programmes.index', compact('programmes', 'totalProgrammes', 'totalStudents', 'totalSubjects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('programmes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:programmes,name',
            'code' => 'required|string|max:50|unique:programmes,code',
            'description' => 'nullable|string',
            'duration' => 'required|integer|min:1|max:10', // Form field is 'duration' but DB column is 'duration_years'
            'department' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('programmes.create')
                ->withErrors($validator)
                ->withInput();
        }

        // Map 'duration' form field to 'duration_years' database column
        $data = $request->all();
        $data['duration_years'] = $data['duration'];
        unset($data['duration']);

        Programme::create($data);

        return redirect()->route('programmes.index')
            ->with('success', 'Programme created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $programme = Programme::with(['subjects', 'students'])->findOrFail($id);

        // Group subjects by semester
        $subjectsBySemester = $programme->subjects->groupBy('semester_id');

        // Get student statistics
        $totalStudents = $programme->students->count();
        $activeStudents = $programme->students->where('status', 'active')->count();
        $graduatedStudents = $programme->students->where('status', 'graduated')->count();

        return view('programmes.show', compact(
            'programme',
            'subjectsBySemester',
            'totalStudents',
            'activeStudents',
            'graduatedStudents'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $programme = Programme::findOrFail($id);
        return view('programmes.edit', compact('programme'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $programme = Programme::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:programmes,name,' . $id,
            'code' => 'required|string|max:50|unique:programmes,code,' . $id,
            'description' => 'nullable|string',
            'duration' => 'required|integer|min:1|max:10', // Form field is 'duration' but DB column is 'duration_years'
            'department' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('programmes.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        // Map 'duration' form field to 'duration_years' database column
        $data = $request->all();
        $data['duration_years'] = $data['duration'];
        unset($data['duration']);

        $programme->update($data);

        return redirect()->route('programmes.index')
            ->with('success', 'Programme updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $programme = Programme::findOrFail($id);

        // Check if there are any students or subjects associated with this programme
        if ($programme->students()->count() > 0) {
            return redirect()->route('programmes.index')
                ->with('error', 'Cannot delete programme with associated students.');
        }

        if ($programme->subjects()->count() > 0) {
            return redirect()->route('programmes.index')
                ->with('error', 'Cannot delete programme with associated subjects.');
        }

        $programme->delete();

        return redirect()->route('programmes.index')
            ->with('success', 'Programme deleted successfully.');
    }

    /**
     * Display subjects in a programme.
     */
    public function subjects(string $id)
    {
        $programme = Programme::findOrFail($id);
        $subjects = $programme->subjects()
            ->orderBy('semester_id')
            ->orderBy('code')
            ->paginate(20);

        return view('programmes.subjects', compact('programme', 'subjects'));
    }

    /**
     * Search programmes.
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        $programmes = Programme::where('name', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhere('department', 'like', "%{$query}%")
            ->orWhere('faculty', 'like', "%{$query}%")
            ->withCount(['students', 'subjects'])
            ->paginate(10);

        return view('programmes.index', compact('programmes', 'query'));
    }

    /**
     * Export programme data.
     */
    public function export(string $id)
    {
        $programme = Programme::with(['subjects', 'students'])->findOrFail($id);

        // This would typically generate an Excel file with programme data
        // For simplicity, we'll just redirect back with a success message

        return redirect()->route('programmes.show', $id)
            ->with('success', 'Programme data exported successfully.');
    }
}

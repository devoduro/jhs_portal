<?php

namespace App\Http\Controllers;

use App\Exports\ClassListTemplateExport;
use App\Imports\ClassListImport;
use App\Models\ClassGroup;
use App\Models\Teacher;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ClassGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $classGroups = ClassGroup::with('classTeacher')
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(20);

        $currentSemesterId = optional(Semester::where('is_current', true)->first())->id;

        return view('class-groups.index', compact('classGroups', 'currentSemesterId'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();

        return view('class-groups.create', compact('teachers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'level' => 'required|integer|' . \App\Support\FormLabel::rule(),
            'name' => 'required|string|max:255|unique:class_groups,name' . (isset($classGroup) ? ',' . $classGroup->id : ''),
            'class_teacher_id' => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('class-groups.create')
                ->withErrors($validator)
                ->withInput();
        }

        ClassGroup::create($request->only(['level', 'name', 'class_teacher_id']));

        return redirect()->route('class-groups.index')
            ->with('success', 'Class created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClassGroup $classGroup)
    {
        $teachers = Teacher::orderBy('name')->get();

        return view('class-groups.edit', compact('classGroup', 'teachers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClassGroup $classGroup)
    {
        $validator = Validator::make($request->all(), [
            'level' => 'required|integer|' . \App\Support\FormLabel::rule(),
            'name' => 'required|string|max:255|unique:class_groups,name' . (isset($classGroup) ? ',' . $classGroup->id : ''),
            'class_teacher_id' => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('class-groups.edit', $classGroup)
                ->withErrors($validator)
                ->withInput();
        }

        $classGroup->update($request->only(['level', 'name', 'class_teacher_id']));

        // Pupils follow their class's level.
        $classGroup->students()->update(['level' => $classGroup->level]);

        return redirect()->route('class-groups.index')
            ->with('success', 'Class updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClassGroup $classGroup)
    {
        if ($classGroup->students()->count() > 0) {
            return redirect()->route('class-groups.index')
                ->with('error', 'Cannot delete a class with students assigned to it.');
        }

        $classGroup->delete();

        return redirect()->route('class-groups.index')
            ->with('success', 'Class deleted successfully.');
    }

    /**
     * Show the form for assigning students to this class group.
     */
    public function assignStudentsForm(ClassGroup $classGroup)
    {
        $students = Student::where('status', 'active')
            ->where(fn ($q) => $q->where('level', $classGroup->level)->orWhere('class_group_id', $classGroup->id))
            ->with('classGroup')
            ->orderBy('full_name')
            ->get();

        $assignedIds = $students->where('class_group_id', $classGroup->id)->pluck('id')->toArray();

        return view('class-groups.assign-students', compact('classGroup', 'students', 'assignedIds'));
    }

    /**
     * Assign the selected students to this class group.
     */
    public function assignStudents(Request $request, ClassGroup $classGroup)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $selectedIds = $request->input('student_ids', []);

        // Unassign students in this class/level/programme who were deselected.
        Student::where('class_group_id', $classGroup->id)
            ->whereNotIn('id', $selectedIds)
            ->update(['class_group_id' => null]);

        // Assign the selected students to this class group (pupils still in school only).
        Student::whereIn('id', $selectedIds)
            ->where('status', 'active')
            ->update(['class_group_id' => $classGroup->id, 'level' => $classGroup->level]);

        return redirect()->route('class-groups.index')
            ->with('success', 'Pupils placed in ' . $classGroup->name . '.');
    }

    /**
     * Show the bulk class-list import form.
     */
    public function importForm()
    {
        return view('class-groups.import');
    }

    /**
     * Handle the bulk import of a class list.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('class-groups.import.form')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $import = new ClassListImport();
            Excel::import($import, $request->file('excel_file'));

            $stats = $import->getStats();
            $message = "Processed {$stats['processed']} record(s), skipped {$stats['skipped']}.";

            if (!empty($stats['errors'])) {
                $message .= ' Issues: ' . implode(' | ', array_slice($stats['errors'], 0, 5));
                if (count($stats['errors']) > 5) {
                    $message .= ' (+' . (count($stats['errors']) - 5) . ' more)';
                }

                return redirect()->route('class-groups.index')->with('warning', $message);
            }

            return redirect()->route('class-groups.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('class-groups.import.form')
                ->with('error', 'Error importing class list: ' . $e->getMessage());
        }
    }

    /**
     * Download the class list import template.
     */
    public function downloadTemplate()
    {
        return Excel::download(new ClassListTemplateExport, 'class_list_template.xlsx');
    }

    /**
     * The old print page - class lists are now printed from Class Lists (any column choice, PDF and Excel).
     */
    public function print(ClassGroup $classGroup)
    {
        return redirect()->route('class-lists.show', $classGroup);
    }
}

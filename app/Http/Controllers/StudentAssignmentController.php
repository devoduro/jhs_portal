<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Student-facing side of the assignment module (inside the existing student
 * portal, gated by the same 'student' middleware as the rest of it).
 */
class StudentAssignmentController extends Controller
{
    protected function currentStudent()
    {
        return Auth::user()->student;
    }

    protected function visibleAssignmentsQuery($student)
    {
        return Assignment::whereHas('classGroups', fn ($q) => $q->where('class_groups.id', $student->class_group_id))
            ->where('is_active', true);
    }

    public function index()
    {
        $student = $this->currentStudent();

        $assignments = $this->visibleAssignmentsQuery($student)->with('subject')->orderBy('due_at')->get();
        $submissions = AssignmentSubmission::where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $grouped = ['pending' => collect(), 'submitted' => collect(), 'graded' => collect(), 'overdue' => collect()];

        foreach ($assignments as $assignment) {
            $submission = $submissions->get($assignment->id);
            $assignment->my_submission = $submission;

            if ($submission && $submission->isGraded()) {
                $grouped['graded']->push($assignment);
            } elseif ($submission && $submission->submitted_at) {
                $grouped['submitted']->push($assignment);
            } elseif ($assignment->isOverdue() && !$assignment->allow_late_submission) {
                $grouped['overdue']->push($assignment);
            } else {
                $grouped['pending']->push($assignment);
            }
        }

        return view('student.assignments.index', ['assignments' => $grouped]);
    }

    public function show(Assignment $assignment)
    {
        $student = $this->currentStudent();
        $this->authorizeVisible($assignment, $student);

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $student = $this->currentStudent();
        $this->authorizeVisible($assignment, $student);

        if (!$assignment->canSubmit()) {
            return back()->with('error', 'This assignment is no longer accepting submissions.');
        }

        $validator = Validator::make($request->all(), [
            'submission_text' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (!$request->filled('submission_text') && !$request->hasFile('file')) {
            return back()->with('error', 'Type something or attach a file before submitting.')->withInput();
        }

        $data = [
            'submission_text' => $request->input('submission_text'),
            'submitted_at' => now(),
            'is_late' => $assignment->isOverdue(),
        ];

        if ($request->hasFile('file')) {
            $existing = AssignmentSubmission::where('assignment_id', $assignment->id)->where('student_id', $student->id)->first();
            if ($existing?->file_path) {
                Storage::disk('public')->delete($existing->file_path);
            }

            $file = $request->file('file');
            $data['file_path'] = $file->store('assignment_submissions', 'public');
            $data['file_name'] = $file->getClientOriginalName();
        }

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            $data
        );

        return redirect()->route('student.assignments.show', $assignment)->with('success', 'Your work has been submitted.');
    }

    protected function authorizeVisible(Assignment $assignment, $student): void
    {
        abort_unless(
            $assignment->classGroups()->where('class_groups.id', $student->class_group_id)->exists(),
            403
        );
    }
}

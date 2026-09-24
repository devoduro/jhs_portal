<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassGroup;
use App\Models\Subject;
use App\Services\AssignmentNotificationService;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Admin/teacher side of the assignment module. A teacher only sees/manages
 * assignments for subjects they're assigned to teach (see ScopesToTeacher),
 * same scoping as quizzes and continuous assessment.
 */
class AssignmentController extends Controller
{
    use ScopesToTeacher;

    public function index(Request $request)
    {
        $query = Assignment::with(['subject', 'classGroups'])->withCount('submissions')->orderByDesc('created_at');

        if ($this->isScopedTeacher()) {
            $query->whereIn('subject_id', $this->teacherSubjectIds());
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        $assignments = $query->paginate(15)->withQueryString();
        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();

        return view('assignments.index', compact('assignments', 'subjects'));
    }

    public function create()
    {
        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();
        $classGroups = ClassGroup::with('programme')->orderBy('level')->orderBy('name')->get();

        return view('assignments.create', compact('subjects', 'classGroups'));
    }

    public function store(Request $request, AssignmentNotificationService $notifier)
    {
        $subjectIds = $this->isScopedTeacher() ? $this->teacherSubjectIds() : Subject::pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'subject_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($subjectIds)],
            'class_group_ids' => 'required|array|min:1',
            'class_group_ids.*' => 'exists:class_groups,id',
            'due_at' => 'required|date|after:now',
            'allow_late_submission' => 'nullable|boolean',
            'max_score' => 'nullable|numeric|min:0',
            'notify_students' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('assignments.create')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $classGroupIds = $data['class_group_ids'];
        unset($data['class_group_ids'], $data['notify_students']);

        $data['created_by'] = Auth::id();
        $data['allow_late_submission'] = $request->boolean('allow_late_submission');
        $data['is_active'] = true;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('assignment_attachments', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $assignment = Assignment::create($data);
        $assignment->classGroups()->sync($classGroupIds);

        $message = 'Assignment created.';

        if ($request->boolean('notify_students', true)) {
            $result = $notifier->notify($assignment);
            $message .= " Emailed {$result['sent']} student(s)".($result['skipped_no_email'] > 0 ? ", {$result['skipped_no_email']} have no email on file" : '').'.';
        }

        return redirect()->route('assignments.show', $assignment)->with('success', $message);
    }

    public function show(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $assignment->load(['subject', 'classGroups']);

        $submissions = $assignment->submissions()->with('student')->get()->keyBy('student_id');
        $eligibleStudents = $assignment->eligibleStudents()->orderBy('full_name')->get();

        $stats = [
            'eligible' => $eligibleStudents->count(),
            'submitted' => $submissions->whereNotNull('submitted_at')->count(),
            'graded' => $submissions->whereNotNull('graded_at')->count(),
            'late' => $submissions->where('is_late', true)->count(),
        ];

        return view('assignments.show', compact('assignment', 'submissions', 'eligibleStudents', 'stats'));
    }

    public function edit(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();
        $classGroups = ClassGroup::with('programme')->orderBy('level')->orderBy('name')->get();
        $selectedClassGroupIds = $assignment->classGroups()->pluck('class_groups.id')->all();

        return view('assignments.edit', compact('assignment', 'subjects', 'classGroups', 'selectedClassGroupIds'));
    }

    public function update(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $subjectIds = $this->isScopedTeacher() ? $this->teacherSubjectIds() : Subject::pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'subject_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($subjectIds)],
            'class_group_ids' => 'required|array|min:1',
            'class_group_ids.*' => 'exists:class_groups,id',
            'due_at' => 'required|date',
            'allow_late_submission' => 'nullable|boolean',
            'max_score' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('assignments.edit', $assignment)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $classGroupIds = $data['class_group_ids'];
        unset($data['class_group_ids']);

        $data['allow_late_submission'] = $request->boolean('allow_late_submission');
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('attachment')) {
            if ($assignment->attachment_path) {
                Storage::disk('public')->delete($assignment->attachment_path);
            }
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('assignment_attachments', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $assignment->update($data);
        $assignment->classGroups()->sync($classGroupIds);

        return redirect()->route('assignments.show', $assignment)->with('success', 'Assignment updated.');
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        if ($assignment->attachment_path) {
            Storage::disk('public')->delete($assignment->attachment_path);
        }

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', 'Assignment deleted.');
    }

    public function notifyNow(Assignment $assignment, AssignmentNotificationService $notifier)
    {
        $this->authorizeAssignment($assignment);

        $result = $notifier->notify($assignment);

        return back()->with('success', "Emailed {$result['sent']} student(s)".($result['skipped_no_email'] > 0 ? ", {$result['skipped_no_email']} have no email on file" : '').'.');
    }

    public function gradeSubmission(Request $request, Assignment $assignment, AssignmentSubmission $submission)
    {
        $this->authorizeAssignment($assignment);
        abort_if($submission->assignment_id !== $assignment->id, 404);

        $validator = Validator::make($request->all(), [
            'score' => 'nullable|numeric|min:0'.($assignment->max_score ? '|max:'.$assignment->max_score : ''),
            'feedback' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $submission->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ]);

        return back()->with('success', 'Submission graded.');
    }

    protected function authorizeAssignment(Assignment $assignment): void
    {
        if ($this->isScopedTeacher() && !in_array($assignment->subject_id, $this->teacherSubjectIds(), true)) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Admin/teacher side of the quiz module: create timed quizzes tied to a
 * subject (optionally narrowed to one class), manage their question bank,
 * and review student attempts. A teacher only sees/manages quizzes for
 * subjects they're assigned to teach (see ScopesToTeacher); Exams Officers
 * and Super Admins see everything.
 */
class QuizController extends Controller
{
    use ScopesToTeacher;

    public function index(Request $request)
    {
        $query = Quiz::with(['subject', 'classGroup'])->withCount(['questions', 'attempts'])->orderByDesc('created_at');

        if ($this->isScopedTeacher()) {
            $query->whereIn('subject_id', $this->teacherSubjectIds());
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        $quizzes = $query->paginate(15)->withQueryString();
        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();

        return view('quizzes.index', compact('quizzes', 'subjects'));
    }

    public function create()
    {
        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();
        $classGroups = ClassGroup::with('programme')->orderBy('level')->orderBy('name')->get();

        return view('quizzes.create', compact('subjects', 'classGroups'));
    }

    public function store(Request $request)
    {
        $subjectIds = $this->isScopedTeacher() ? $this->teacherSubjectIds() : Subject::pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'subject_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($subjectIds)],
            'class_group_id' => 'nullable|exists:class_groups,id',
            'duration_minutes' => 'required|integer|min:1|max:300',
            'pass_percentage' => 'required|numeric|min:0|max:100',
            'has_negative_marking' => 'nullable|boolean',
            'negative_mark_value' => 'required_if:has_negative_marking,1|nullable|numeric|min:0',
            'shuffle_questions' => 'nullable|boolean',
            'allow_retake' => 'nullable|boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
        ]);

        $this->checkClassLevel($validator, $request);

        if ($validator->fails()) {
            return redirect()->route('quizzes.create')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();
        $data['has_negative_marking'] = $request->boolean('has_negative_marking');
        $data['shuffle_questions'] = $request->boolean('shuffle_questions', true);
        $data['allow_retake'] = $request->boolean('allow_retake');
        $data['is_active'] = true;

        $quiz = Quiz::create($data);

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Quiz created. Now add some questions.');
    }

    /**
     * A quiz aimed at one class must be for a subject taught at that class's level.
     */
    protected function checkClassLevel($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            if (!$request->filled('class_group_id') || !$request->filled('subject_id')) {
                return;
            }

            $class = ClassGroup::find($request->class_group_id);
            $subject = Subject::find($request->subject_id);

            if ($class && $subject && (int) $class->level !== (int) $subject->level) {
                $validator->errors()->add('class_group_id', "{$subject->title} is not taught in {$class->name} (it is a " . \App\Support\FormLabel::label($subject->level) . ' subject).');
            }
        });
    }

    public function show(Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $quiz->load(['subject', 'classGroup', 'questions']);

        $attempts = $quiz->attempts()->with('student')->orderByDesc('created_at')->get();
        $completedAttempts = $attempts->where('status', QuizAttempt::STATUS_COMPLETED);

        $stats = [
            'eligible' => $quiz->eligibleStudents()->count(),
            'attempted' => $attempts->pluck('student_id')->unique()->count(),
            'completed' => $completedAttempts->count(),
            'average_percentage' => $completedAttempts->isNotEmpty() ? round($completedAttempts->avg('percentage'), 1) : null,
            'pass_rate' => $completedAttempts->isNotEmpty() ? round($completedAttempts->where('is_passed', true)->count() / $completedAttempts->count() * 100, 1) : null,
        ];

        return view('quizzes.show', compact('quiz', 'attempts', 'stats'));
    }

    public function edit(Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $subjects = $this->scopeToTeacher(Subject::query())->orderBy('code')->get();
        $classGroups = ClassGroup::with('programme')->orderBy('level')->orderBy('name')->get();

        return view('quizzes.edit', compact('quiz', 'subjects', 'classGroups'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $subjectIds = $this->isScopedTeacher() ? $this->teacherSubjectIds() : Subject::pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'subject_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($subjectIds)],
            'class_group_id' => 'nullable|exists:class_groups,id',
            'duration_minutes' => 'required|integer|min:1|max:300',
            'pass_percentage' => 'required|numeric|min:0|max:100',
            'has_negative_marking' => 'nullable|boolean',
            'negative_mark_value' => 'required_if:has_negative_marking,1|nullable|numeric|min:0',
            'shuffle_questions' => 'nullable|boolean',
            'allow_retake' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
        ]);

        $this->checkClassLevel($validator, $request);

        if ($validator->fails()) {
            return redirect()->route('quizzes.edit', $quiz)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['has_negative_marking'] = $request->boolean('has_negative_marking');
        $data['shuffle_questions'] = $request->boolean('shuffle_questions');
        $data['allow_retake'] = $request->boolean('allow_retake');
        $data['is_active'] = $request->boolean('is_active');

        $quiz->update($data);

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $quiz->delete();

        return redirect()->route('quizzes.index')->with('success', 'Quiz deleted.');
    }

    // ── Questions ──────────────────────────────────────────────

    public function createQuestion(Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        return view('quizzes.questions.create', compact('quiz'));
    }

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        [$data, $error] = $this->validateQuestion($request);

        if ($error) {
            return redirect()->route('quizzes.questions.create', $quiz)->withErrors($error)->withInput();
        }

        $data['quiz_id'] = $quiz->id;
        $data['sort_order'] = $quiz->questions()->max('sort_order') + 1;

        QuizQuestion::create($data);

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Question added.');
    }

    public function editQuestion(Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeQuiz($quiz);
        abort_if($question->quiz_id !== $quiz->id, 404);

        return view('quizzes.questions.edit', compact('quiz', 'question'));
    }

    public function updateQuestion(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeQuiz($quiz);
        abort_if($question->quiz_id !== $quiz->id, 404);

        [$data, $error] = $this->validateQuestion($request);

        if ($error) {
            return redirect()->route('quizzes.questions.edit', [$quiz, $question])->withErrors($error)->withInput();
        }

        $question->update($data);

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Question updated.');
    }

    public function destroyQuestion(Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeQuiz($quiz);
        abort_if($question->quiz_id !== $quiz->id, 404);

        $question->delete();

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Question removed.');
    }

    protected function validateQuestion(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'type' => 'required|in:mcq,true_false',
            'options' => 'required_if:type,mcq|array|min:2',
            'options.*' => 'required_if:type,mcq|string|max:500',
            // For mcq this is the 0-based index of the option row the form submitted (which
            // option's radio was checked); for true_false it's the literal key "true"/"false".
            'correct_answer' => 'required|string',
            'marks' => 'required|numeric|min:0.5',
            'explanation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return [null, $validator];
        }

        $data = $validator->validated();

        if ($data['type'] === 'true_false') {
            $data['options'] = ['true' => 'True', 'false' => 'False'];
        } else {
            // Re-key A, B, C... so option order is stable and correct_answer (an option's
            // submitted position) lines up with what gets stored.
            $optionValues = array_values($data['options']);
            $data['options'] = array_combine(
                array_map(fn ($i) => chr(65 + $i), array_keys($optionValues)),
                $optionValues
            );

            if (ctype_digit($data['correct_answer']) && isset($optionValues[(int) $data['correct_answer']])) {
                $data['correct_answer'] = chr(65 + (int) $data['correct_answer']);
            }
        }

        if (!array_key_exists($data['correct_answer'], $data['options'])) {
            return [null, ['correct_answer' => ['Select which option is correct.']]];
        }

        return [$data, null];
    }

    public function results(Quiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $attempts = $quiz->attempts()
            ->with('student')
            ->orderByDesc('percentage')
            ->get();

        return view('quizzes.results', compact('quiz', 'attempts'));
    }

    /**
     * A teacher may only manage quizzes for subjects they're assigned to teach.
     */
    protected function authorizeQuiz(Quiz $quiz): void
    {
        if ($this->isScopedTeacher() && !in_array($quiz->subject_id, $this->teacherSubjectIds(), true)) {
            abort(403);
        }
    }
}

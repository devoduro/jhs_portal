<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Services\QuizGradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Student-facing side of the quiz module (inside the existing student portal,
 * gated by the same 'student' middleware as the rest of it - see routes/web.php).
 */
class StudentQuizController extends Controller
{
    protected function currentStudent()
    {
        return Auth::user()->student;
    }

    protected function eligibleSubjectIds($student): array
    {
        return Subject::where('level', $student->level)
            ->whereHas('programmes', fn ($q) => $q->where('programmes.id', $student->programme_id))
            ->pluck('id')
            ->all();
    }

    protected function visibleQuizzesQuery($student)
    {
        return Quiz::whereIn('subject_id', $this->eligibleSubjectIds($student))
            ->where(function ($q) use ($student) {
                $q->whereNull('class_group_id')->orWhere('class_group_id', $student->class_group_id);
            });
    }

    public function index()
    {
        $student = $this->currentStudent();

        $quizzes = $this->visibleQuizzesQuery($student)
            ->where('is_active', true)
            ->with('subject')
            ->withCount('questions')
            ->get();

        $attempts = QuizAttempt::where('student_id', $student->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('quiz_id');

        $grouped = ['available' => collect(), 'ongoing' => collect(), 'completed' => collect(), 'upcoming' => collect()];

        foreach ($quizzes as $quiz) {
            $quizAttempts = $attempts->get($quiz->id, collect());
            $latest = $quizAttempts->first();
            $quiz->latest_attempt = $latest;

            if ($latest && $latest->status === QuizAttempt::STATUS_IN_PROGRESS && !$latest->isExpired()) {
                $grouped['ongoing']->push($quiz);
                continue;
            }

            if ($quizAttempts->contains(fn ($a) => $a->status === QuizAttempt::STATUS_COMPLETED) && !$quiz->allow_retake) {
                $grouped['completed']->push($quiz);
                continue;
            }

            if ($quiz->start_at && now()->lt($quiz->start_at)) {
                $grouped['upcoming']->push($quiz);
                continue;
            }

            if ($quiz->end_at && now()->gt($quiz->end_at)) {
                $grouped['completed']->push($quiz);
                continue;
            }

            $grouped['available']->push($quiz);
        }

        return view('student.quizzes.index', ['quizzes' => $grouped]);
    }

    public function show(Quiz $quiz)
    {
        $student = $this->currentStudent();
        $this->authorizeVisible($quiz, $student);

        $quiz->loadCount('questions');
        $lastAttempt = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $student->id)->latest('created_at')->first();

        return view('student.quizzes.show', compact('quiz', 'lastAttempt'));
    }

    public function start(Quiz $quiz)
    {
        $student = $this->currentStudent();
        $this->authorizeVisible($quiz, $student);

        if (!$quiz->isOpen()) {
            return redirect()->route('student.quizzes.index')->with('error', 'This quiz is not open right now.');
        }

        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->latest('created_at')
            ->first();

        if ($existing) {
            if ($existing->status === QuizAttempt::STATUS_IN_PROGRESS && !$existing->isExpired()) {
                return redirect()->route('student.quizzes.take', $quiz);
            }

            if ($existing->status === QuizAttempt::STATUS_COMPLETED && !$quiz->allow_retake) {
                return redirect()->route('student.quizzes.result', $existing);
            }
        }

        if ($quiz->questions()->count() === 0) {
            return redirect()->route('student.quizzes.index')->with('error', 'This quiz has no questions yet.');
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'ends_at' => now()->addMinutes($quiz->duration_minutes),
        ]);

        return redirect()->route('student.quizzes.take', $quiz);
    }

    public function take(Quiz $quiz)
    {
        $student = $this->currentStudent();
        $this->authorizeVisible($quiz, $student);

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->latest('created_at')
            ->first();

        if (!$attempt) {
            return redirect()->route('student.quizzes.show', $quiz);
        }

        if ($attempt->isExpired()) {
            app(QuizGradingService::class)->grade($attempt);

            return redirect()->route('student.quizzes.result', $attempt);
        }

        $questions = $quiz->questions;
        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        $existingAnswers = QuizAnswer::where('quiz_attempt_id', $attempt->id)->pluck('selected_option', 'quiz_question_id');

        return view('student.quizzes.take', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $questions,
            'existingAnswers' => $existingAnswers,
            'secondsRemaining' => $attempt->secondsRemaining(),
        ]);
    }

    public function saveAnswer(Request $request, Quiz $quiz)
    {
        $student = $this->currentStudent();

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->latest('created_at')
            ->firstOrFail();

        if ($attempt->isExpired()) {
            return response()->json(['status' => 'expired'], 403);
        }

        $question = $quiz->questions()->findOrFail($request->input('question_id'));

        QuizAnswer::updateOrCreate(
            ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
            ['selected_option' => (string) $request->input('selected_option')]
        );

        return response()->json(['status' => 'saved']);
    }

    public function submit(Quiz $quiz)
    {
        $student = $this->currentStudent();

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->latest('created_at')
            ->first();

        if (!$attempt) {
            return redirect()->route('student.quizzes.index');
        }

        app(QuizGradingService::class)->grade($attempt);

        return redirect()->route('student.quizzes.result', $attempt);
    }

    public function result(QuizAttempt $attempt)
    {
        $student = $this->currentStudent();
        abort_if($attempt->student_id !== $student->id, 403);

        if ($attempt->status === QuizAttempt::STATUS_IN_PROGRESS) {
            if (!$attempt->isExpired()) {
                return redirect()->route('student.quizzes.take', $attempt->quiz_id);
            }
            app(QuizGradingService::class)->grade($attempt);
            $attempt = $attempt->fresh();
        }

        $attempt->load(['quiz.subject', 'answers.question']);

        $breakdown = $attempt->quiz->questions->map(function ($question) use ($attempt) {
            $answer = $attempt->answers->firstWhere('quiz_question_id', $question->id);

            return [
                'question' => $question,
                'answer' => $answer,
                'status' => !$answer || !$answer->selected_option ? 'skipped' : ($answer->is_correct ? 'correct' : 'wrong'),
            ];
        });

        return view('student.quizzes.result', compact('attempt', 'breakdown'));
    }

    protected function authorizeVisible(Quiz $quiz, $student): void
    {
        abort_unless(
            in_array($quiz->subject_id, $this->eligibleSubjectIds($student), true)
                && ($quiz->class_group_id === null || $quiz->class_group_id === $student->class_group_id),
            403
        );
    }
}

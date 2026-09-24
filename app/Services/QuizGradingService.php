<?php

namespace App\Services;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;

/**
 * Grades a completed attempt: each answer is checked against its question's
 * correct_answer (case-insensitive key match), awarding that question's marks
 * when correct and deducting the quiz's negative-mark value when wrong (if
 * negative marking is enabled). The net score never drops below zero.
 */
class QuizGradingService
{
    public function grade(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->loadMissing(['answers', 'quiz.questions']);

        $quiz = $attempt->quiz;
        $answersByQuestion = $attempt->answers->keyBy('quiz_question_id');

        $netScore = 0;
        $correctCount = 0;
        $totalMarks = 0;

        foreach ($quiz->questions as $question) {
            $totalMarks += (float) $question->marks;
            $answer = $answersByQuestion->get($question->id);

            if (!$answer || $answer->selected_option === null || $answer->selected_option === '') {
                continue;
            }

            $isCorrect = strtolower(trim((string) $answer->selected_option)) === strtolower(trim((string) $question->correct_answer));
            $marksAwarded = 0;

            if ($isCorrect) {
                $marksAwarded = (float) $question->marks;
                $correctCount++;
            } elseif ($quiz->has_negative_marking) {
                $marksAwarded = -abs((float) $quiz->negative_mark_value);
            }

            $answer->update([
                'is_correct' => $isCorrect,
                'marks_awarded' => $marksAwarded,
            ]);

            $netScore += $marksAwarded;
        }

        $netScore = max(0, $netScore);
        $percentage = $totalMarks > 0 ? round(($netScore / $totalMarks) * 100, 2) : 0;

        $attempt->update([
            'status' => QuizAttempt::STATUS_COMPLETED,
            'submitted_at' => $attempt->submitted_at ?? now(),
            'total_marks' => $totalMarks,
            'score' => $netScore,
            'correct_count' => $correctCount,
            'percentage' => $percentage,
            'is_passed' => $percentage >= (float) $quiz->pass_percentage,
        ]);

        return $attempt->fresh();
    }
}

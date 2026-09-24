<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = [
        'quiz_attempt_id',
        'quiz_question_id',
        'selected_option',
        'is_correct',
        'marks_awarded',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
        ];
    }

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_id',
        'question_text',
        'type',
        'options',
        'correct_answer',
        'marks',
        'explanation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'marks' => 'decimal:2',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function correctAnswerText(): ?string
    {
        return $this->options[$this->correct_answer] ?? null;
    }
}

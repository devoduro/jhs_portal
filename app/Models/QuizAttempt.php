<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'quiz_id',
        'student_id',
        'status',
        'started_at',
        'ends_at',
        'submitted_at',
        'total_marks',
        'score',
        'correct_count',
        'percentage',
        'is_passed',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'total_marks' => 'decimal:2',
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isExpired(): bool
    {
        return now()->gt($this->ends_at);
    }

    public function secondsRemaining(): int
    {
        return max(0, now()->diffInSeconds($this->ends_at, false));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'title',
        'description',
        'instructions',
        'subject_id',
        'class_group_id',
        'created_by',
        'duration_minutes',
        'pass_percentage',
        'has_negative_marking',
        'negative_mark_value',
        'shuffle_questions',
        'allow_retake',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_negative_marking' => 'boolean',
            'shuffle_questions' => 'boolean',
            'allow_retake' => 'boolean',
            'is_active' => 'boolean',
            'pass_percentage' => 'decimal:2',
            'negative_mark_value' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classGroup()
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function totalMarks(): float
    {
        return (float) $this->questions()->sum('marks');
    }

    /**
     * Active, within its scheduling window (if any).
     */
    public function isOpen(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at && $now->gt($this->end_at)) {
            return false;
        }

        return true;
    }

    /**
     * Students eligible to take this quiz - same "enrolled in this subject" logic as
     * Subject::students() (active, at this quiz's level, in a programme the subject is
     * offered on), narrowed further to one class if the quiz targets a specific class.
     */
    public function eligibleStudents()
    {
        $query = Student::where('status', 'active')
            ->where('level', $this->subject->level)
            ->whereIn('programme_id', $this->subject->programmes()->pluck('programmes.id'));

        if ($this->class_group_id) {
            $query->where('class_group_id', $this->class_group_id);
        }

        return $query;
    }
}

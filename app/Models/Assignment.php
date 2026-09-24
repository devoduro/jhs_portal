<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'title',
        'description',
        'attachment_path',
        'attachment_name',
        'subject_id',
        'created_by',
        'due_at',
        'allow_late_submission',
        'max_score',
        'is_active',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allow_late_submission' => 'boolean',
            'is_active' => 'boolean',
            'max_score' => 'decimal:2',
            'notified_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classGroups()
    {
        return $this->belongsToMany(ClassGroup::class, 'assignment_class_groups');
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isOverdue(): bool
    {
        return now()->gt($this->due_at);
    }

    public function canSubmit(): bool
    {
        return $this->is_active && (!$this->isOverdue() || $this->allow_late_submission);
    }

    /**
     * Every student in any of this assignment's target classes.
     */
    public function eligibleStudents()
    {
        return Student::where('status', 'active')
            ->whereIn('class_group_id', $this->classGroups()->pluck('class_groups.id'));
    }
}

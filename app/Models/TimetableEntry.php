<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'class_group_id',
        'subject_id',
        'teacher_id',
        'venue_id',
        'semester_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_virtual',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_virtual' => 'boolean',
    ];

    /**
     * Get the class group this lesson slot belongs to.
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * Get the subject taught in this lesson slot.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher assigned to this specific lesson slot.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the venue this lesson slot is held in.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Get the semester this lesson slot belongs to.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the attendance records captured for this lesson slot.
     */
    public function lessonAttendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }
}

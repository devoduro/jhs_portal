<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'programme_id',
        'level',
        'name',
        'class_teacher_id',
    ];

    protected $casts = [
        'level' => 'integer',
        'class_teacher_id' => 'integer',
    ];

    /**
     * Get the programme this class group belongs to.
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * The teacher in charge of this class (enters attendance, conduct and remarks for the
     * terminal report).
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function levelLabel(): string
    {
        return \App\Support\FormLabel::label($this->level);
    }

    /**
     * Get the students assigned to this class group.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get the timetable entries for this class group.
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}

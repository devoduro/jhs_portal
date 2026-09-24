<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'description',
        'credit_hours',
        'level',
        'semester_id',
        'is_core',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'credit_hours' => 'decimal:2',
        'is_core' => 'boolean',
    ];

    /**
     * Get the programmes associated with the subject.
     */
    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'subject_programme');
    }

    /**
     * Get the semester that owns the subject.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the results for the subject.
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * Get the teachers assigned to teach this subject. A subject can have more than one
     * teacher (e.g. different teachers teaching different sections of the same subject).
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'subject_teacher');
    }

    /**
     * Get the timetable entries (scheduled lesson slots) for this subject.
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * Get the prerequisite subject for this subject.
     */
    public function prerequisite()
    {
        return $this->belongsTo(Subject::class, 'prerequisite_id');
    }

    /**
     * Get the subjects that have this subject as their prerequisite.
     */
    public function dependentSubjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'prerequisite_id');
    }

    /**
     * Build a per-class subject code from a base prefix, e.g. buildCode('ENG', 6) -> "ENG-B1",
     * buildCode('MATH', 12) -> "MATH-J1". Used when one subject is created for several class
     * levels at once (SubjectController::store()).
     */
    public static function buildCode(string $prefix, int $level): string
    {
        return strtoupper(trim($prefix)) . '-' . \App\Support\FormLabel::shortCode($level);
    }

    /**
     * Pupils who take this subject: every active pupil at the subject's class level.
     */
    public function students()
    {
        return Student::where('status', 'active')
            ->where('level', $this->level);
    }
}

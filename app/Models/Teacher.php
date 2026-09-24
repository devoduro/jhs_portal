<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teacher extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'staff_id',
        'department_id',
        'profile_photo',
    ];

    /**
     * Get the department this teacher belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the initials for this teacher's name (e.g. "John Kwame Mensah" -> "JKM"),
     * used on the compact timetable grid where full names don't fit.
     */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim($this->name));
        $letters = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), array_filter($words));

        return implode('', array_slice($letters, 0, 3));
    }

    /**
     * Get the portal user account linked to this teacher, if any.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the subjects this teacher is assigned to teach. A teacher can teach more than
     * one subject, and a subject can be co-taught by more than one teacher.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher');
    }

    /**
     * Get the timetable entries this teacher is assigned to.
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * Get the houses this teacher has been assigned to manage as house master.
     */
    public function housesManaged(): HasMany
    {
        return $this->hasMany(House::class, 'house_master_id');
    }
}

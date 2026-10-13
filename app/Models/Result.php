<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'class_group_id',
        'subject_id',
        'semester_id',
        'academic_year_id',
        'class_score',
        'exam_score',
        'grade',
        'grade_point',
        'score',
        'remark',
        'is_repeated',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'grade_point' => 'decimal:2',
        'score' => 'decimal:2',
        'class_score' => 'decimal:2',
        'exam_score' => 'decimal:2',
        'is_repeated' => 'boolean',
    ];
    
    /**
     * Get the student that owns the result.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    /**
     * Per-column marks when the class level is scored on admin-defined components.
     */
    public function componentScores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ResultComponentScore::class);
    }

    /**
     * The class the pupil was in when this result was recorded (positions are ranked within it).
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * Get the subject that owns the result.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
    
    /**
     * Get the semester that owns the result.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
    
    /**
     * Get the academic year that owns the result.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}

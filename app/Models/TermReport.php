<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pupil's non-academic record for one term - attendance, conduct, attitude, interest,
 * the class teacher's and head teacher's remarks, and (in the final term) the class the pupil
 * is promoted to. Printed on the terminal report alongside the subject scores.
 */
class TermReport extends Model
{
    protected $fillable = [
        'student_id',
        'semester_id',
        'class_group_id',
        'days_present',
        'conduct',
        'attitude',
        'interest',
        'class_teacher_remark',
        'head_teacher_remark',
        'promoted_to',
    ];

    protected $casts = [
        'days_present' => 'integer',
        'promoted_to' => 'integer',
    ];

    public const CONDUCT_OPTIONS = [
        'Excellent', 'Very Good', 'Good', 'Satisfactory', 'Fair', 'Needs Improvement',
    ];

    public const ATTITUDE_OPTIONS = [
        'Very hardworking', 'Hardworking', 'Punctual and attentive', 'Respectful', 'Cooperative',
        'Obedient', 'Quiet but attentive', 'Easily distracted', 'Talkative in class', 'Needs to be more serious',
    ];

    public const INTEREST_OPTIONS = [
        'Reading', 'Mathematics', 'Science', 'Sports', 'Music and Dance', 'Drawing and Art', 'ICT',
        'Drama', 'Debating', 'Football', 'Athletics', 'Cultural Activities',
    ];

    public const TEACHER_REMARKS = [
        'An excellent performance. Keep it up!',
        'A very good performance. Keep working hard.',
        'Good work. You can do better with more effort.',
        'A fair performance. Put in more effort next term.',
        'Weak performance. Needs to be more serious with studies.',
        'Has improved greatly. Keep it up.',
        'Performance has dropped. Needs close supervision at home.',
    ];

    public const HEAD_REMARKS = [
        'Excellent result. Keep it up.',
        'Very good. Aim higher.',
        'Good. There is room for improvement.',
        'Fair. More effort is required.',
        'Poor. Parents should give more attention to the child\'s studies.',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }
}

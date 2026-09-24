<?php

namespace App\Traits;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ScopesToTeacher
{
    /**
     * Determine whether the authenticated user is restricted to their own assigned subjects.
     */
    protected function isScopedTeacher(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole('Teacher') && !$user->hasRole('Super Admin') && !$user->hasRole('Exams Officer');
    }

    /**
     * Get the teacher profile ID linked to the authenticated user, if any.
     */
    protected function authTeacherId(): ?int
    {
        return Auth::user()?->teacher_id;
    }

    /**
     * Get the subject IDs the authenticated teacher is allowed to manage.
     */
    protected function teacherSubjectIds(): array
    {
        $teacherId = $this->authTeacherId();

        if (!$teacherId) {
            return [];
        }

        return Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))
            ->pluck('id')
            ->toArray();
    }

    /**
     * Scope a subject query to only subjects the authenticated teacher is assigned to
     * (one of possibly several teachers on that subject), unless the user has broader
     * subject access.
     */
    protected function scopeToTeacher(Builder $query): Builder
    {
        if ($this->isScopedTeacher()) {
            // No linked teacher profile yet -> show nothing rather than everything.
            $query->whereHas('teachers', fn ($q) => $q->where('teachers.id', $this->authTeacherId() ?? 0));
        }

        return $query;
    }

    /**
     * Get the distinct IDs of students enrolled in the authenticated teacher's subjects
     * (i.e. "the students they teach"), for scoping student pickers/filters. A student
     * is enrolled in a subject automatically by programme+level match (see
     * Subject::students()) - SHS students don't individually register for subjects.
     */
    protected function teacherStudentIds(): array
    {
        $subjectIds = $this->teacherSubjectIds();

        if (empty($subjectIds)) {
            return [];
        }

        $studentIds = collect();

        foreach (Subject::whereIn('id', $subjectIds)->get() as $subject) {
            $studentIds = $studentIds->merge($subject->students()->pluck('id'));
        }

        return $studentIds->unique()->values()->toArray();
    }
}

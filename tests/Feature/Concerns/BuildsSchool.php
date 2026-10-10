<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\BasicSchoolSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A small basic school for feature tests: the current academic year with three terms,
 * GES subjects and grading, one class teacher with Basic 4A, a JHS 1 class, and pupils.
 */
trait BuildsSchool
{
    protected Semester $term;
    protected ClassGroup $basic4;
    protected ClassGroup $jhs1;
    protected Teacher $teacher;

    protected function buildSchool(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'is_current' => true]);
        foreach ([1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'] as $n => $name) {
            Semester::create([
                'academic_year_id' => $year->id,
                'name' => $name,
                'semester_number' => $n,
                'start_date' => now()->subDays(20)->addMonths(($n - 1) * 4),
                'end_date' => now()->addDays(60)->addMonths(($n - 1) * 4),
                'is_current' => $n === 1,
            ]);
        }
        $this->term = Semester::where('is_current', true)->first();
        $this->term->update(['vacation_date' => now()->addDays(55), 'next_term_begins' => now()->addDays(80), 'attendance_days' => 60]);

        $this->seed(BasicSchoolSeeder::class);

        $this->teacher = Teacher::create(['name' => 'Ama Owusu', 'email' => 'ama@example.test', 'staff_id' => 'T001']);
        $this->basic4 = ClassGroup::create(['name' => 'Basic 4A', 'level' => 9, 'class_teacher_id' => $this->teacher->id]);
        $this->jhs1 = ClassGroup::create(['name' => 'JHS 1 Gold', 'level' => 12]);
    }

    protected function pupil(string $name, ClassGroup $class, string $gender = 'Male', array $extra = []): Student
    {
        static $n = 0;
        $n++;

        return Student::create($extra + [
            'index_number' => 'SCH/2026/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'gender' => $gender,
            'class_group_id' => $class->id,
            'level' => $class->level,
            'status' => 'active',
            'emergency_contact_name' => 'Parent of ' . $name,
            'emergency_contact_phone' => '0244' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
        ]);
    }

    protected function staff(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => User::ROLE_ADMIN, 'first_login' => false]);
        $user->assignRole($role);

        return $user;
    }

    protected function teacherUser(): User
    {
        return $this->staff('Teacher', ['teacher_id' => $this->teacher->id, 'name' => 'Ama Owusu']);
    }

    protected function pupilUser(Student $student): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'student_id' => $student->id,
            'name' => $student->full_name,
            'first_login' => false,
        ]);
    }
}

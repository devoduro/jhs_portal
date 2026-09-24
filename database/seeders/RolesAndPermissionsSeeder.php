<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Module-level permissions available for assignment to roles.
     *
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'manage-students',
        'view-student-directory',
        'manage-programmes',
        'manage-semesters',
        'manage-subjects',
        'manage-results',
        'view-results',
        'manage-fees',
        'manage-transcripts',
        'view-reports',
        'send-sms',
        'manage-settings',
        'manage-users',
        'manage-roles',
        'view-activity-logs',
        'manage-import-export',
        'manage-biometric',
        'manage-classes',
        'manage-timetable',
        'manage-continuous-assessment',
        'manage-houses',
        'manage-admissions',
        'manage-quizzes',
        'manage-assignments',
    ];

    /**
     * Default role => permissions mapping seeded out of the box.
     * Super Admin is granted every permission below regardless of this list.
     *
     * @var array<string, array<int, string>>
     */
    public const DEFAULT_ROLES = [
        'Super Admin' => [],
        'Accountant' => ['manage-fees', 'view-reports', 'view-student-directory'],
        'Principal' => ['view-reports'],
        'Exams Officer' => ['manage-results', 'manage-subjects', 'manage-transcripts', 'manage-classes', 'manage-timetable', 'manage-continuous-assessment', 'manage-admissions', 'manage-quizzes', 'manage-assignments'],
        'Teacher' => ['view-results', 'manage-continuous-assessment', 'manage-quizzes', 'manage-assignments'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::DEFAULT_ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $rolePermissions = $roleName === 'Super Admin' ? self::PERMISSIONS : $permissions;
            $role->syncPermissions($rolePermissions);
        }

        // Grandfather in truly roleless legacy admin accounts as Super Admin so they don't
        // lose access after this rollout. Scoped to accounts with NO Spatie role at all
        // (not just "doesn't have Super Admin") - every staff account uses the same
        // top-level role=admin column regardless of their real job (Teacher, Accountant,
        // Exams Officer...), so the original `!hasRole($superAdmin)` check re-granted
        // Super Admin to every one of them on every reseed, silently overwriting whatever
        // lesser role had actually been assigned.
        $superAdmin = Role::where('name', 'Super Admin')->first();
        User::where('role', User::ROLE_ADMIN)->get()->each(function (User $user) use ($superAdmin) {
            if ($user->roles->isEmpty()) {
                $user->assignRole($superAdmin);
            }
        });
    }
}

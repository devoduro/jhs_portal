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
        'manage-semesters',
        'manage-subjects',
        'manage-results',
        'view-results',
        'enter-scores',
        'manage-term-reports',
        'manage-fees',
        'view-reports',
        'send-sms',
        'manage-settings',
        'manage-users',
        'manage-roles',
        'view-activity-logs',
        'manage-import-export',
        'manage-classes',
        'manage-timetable',
        'manage-continuous-assessment',
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
        'Head Teacher' => ['view-reports', 'view-results', 'manage-term-reports', 'view-student-directory'],
        'Exams Officer' => ['manage-results', 'view-results', 'enter-scores', 'manage-term-reports', 'manage-subjects', 'manage-classes', 'manage-timetable', 'manage-continuous-assessment', 'manage-quizzes', 'manage-assignments'],
        // Teachers only see the classes/subjects they are assigned to (ScoreSheetController,
        // TermReportController scope them), so the same permissions are safe to hand out.
        'Teacher' => ['view-results', 'enter-scores', 'manage-term-reports', 'manage-continuous-assessment', 'manage-quizzes', 'manage-assignments'],
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

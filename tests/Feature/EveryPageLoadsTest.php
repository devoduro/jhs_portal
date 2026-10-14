<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * Sweep: every GET screen in the system, opened as the Super Admin against a small school,
 * must load without an error. Catches broken views, bad queries and missing variables in any module.
 */
class EveryPageLoadsTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    /** Routes that stream files, hit external services or need special state - covered in their module tests. */
    private const SKIP = ['login', 'logout', 'password.', 'student.', 'sanctum', 'storage.', 'settings.backup.download', 'report-card.show'];

    public function test_every_admin_get_page_loads(): void
    {
        $this->buildSchool();
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $admin = $this->staff('Super Admin');
        $this->actingAs($admin);

        $models = [
            'student' => $kofi, 'id' => $kofi, 'classGroup' => $this->basic4, 'class_group' => $this->basic4,
            'teacher' => $this->teacher, 'semester' => $this->term, 'subject' => Subject::where('level', 9)->first(),
            'academicYear' => $this->term->academicYear, 'user' => $admin, 'level' => 9,
            'department' => Department::create(['name' => 'Languages']),
            'venue' => Venue::create(['name' => 'Room 1', 'capacity' => 40]),
        ];

        $failures = [];
        $visited = 0;

        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true) || !$route->getName() || str_starts_with($route->getName(), 'debugbar')) {
                continue;
            }
            if (collect(self::SKIP)->contains(fn ($s) => str_starts_with($route->getName(), $s))) {
                continue;
            }
            // Only screens behind the admin/staff login.
            if (!collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'permission:') || $m === 'admin'))) {
                continue;
            }

            $params = [];
            foreach ($route->parameterNames() as $name) {
                if (!array_key_exists($name, $models)) {
                    continue 2;   // a screen needing a record we have not built here
                }
                $params[$name] = $models[$name];
            }

            $response = $this->get(route($route->getName(), $params));
            $visited++;

            if ($response->getStatusCode() >= 500) {
                $failures[] = $route->getName() . ' => ' . $response->getStatusCode() . ' ' . substr(strip_tags((string) ($response->exception?->getMessage() ?? '')), 0, 160);
            }
        }

        $this->assertGreaterThan(60, $visited, 'The sweep should cover most screens.');
        $this->assertSame([], $failures, "Screens that errored:\n" . implode("\n", $failures));
    }
}

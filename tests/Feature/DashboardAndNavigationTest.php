<?php

namespace Tests\Feature;

use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class DashboardAndNavigationTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    public function test_admin_dashboard_shows_hero_fees_and_report_progress(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $admin = $this->staff('Super Admin', ['name' => 'Kwame Nkrumah']);
        $this->actingAs($admin)->post(route('fees.charges.store', $kofi), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000]);
        $this->post(route('fees.payments.store', $kofi), ['semester_id' => $this->term->id, 'amount' => 400, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Kwame Nkrumah')
            ->assertSee('Collected Today')
            ->assertSee('GH₵ 400.00')
            ->assertSee('Owed by Parents')
            ->assertSee('Terminal report progress')
            ->assertSee('Basic 4A')
            ->assertSee('dailyChart')
            ->assertSee('classChart')
            ->assertSee('methodChart')
            ->assertSee('Billed by fee item')
            ->assertViewHas('fees', fn ($f) => $f['byClass']->firstWhere('class', 'Basic 4A')['collected'] == 400 && $f['methods']->first()['method'] === 'Cash');
    }

    public function test_accountant_dashboard_hides_academic_panels(): void
    {
        $this->actingAs($this->staff('Accountant'))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Collected This Term')
            ->assertDontSee('Terminal report progress');
    }

    public function test_head_teacher_dashboard_has_no_fee_panels(): void
    {
        $this->actingAs($this->staff('Head Teacher'))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Collected Today')
            ->assertSee('Terminal report progress');
    }

    public function test_teacher_dashboard_lists_own_class(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);

        $this->actingAs($this->teacherUser())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Classes')
            ->assertSee('Basic 4A')
            ->assertDontSee('JHS 1 Gold');
    }

    public function test_pupil_dashboard_shows_position_and_fees(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->actingAs($this->staff('Exams Officer'));
        foreach (Subject::where('level', 9)->take(3)->get() as $subject) {
            $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [
                $kofi->id => ['class_score' => 40, 'exam_score' => 40], $akua->id => ['class_score' => 30, 'exam_score' => 30],
            ]]);
        }
        $this->actingAs($this->staff('Accountant'))->post(route('fees.charges.store', $kofi), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 800]);

        $this->actingAs($this->pupilUser($kofi))->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Last Position')
            ->assertSee('1st / 2')
            ->assertSee('GH₵ 800.00');

        $this->get(route('student.fees.index'))->assertOk();
        $this->get(route('student.report-card', ['semester_id' => $this->term->id]))->assertOk()->assertSee('1st out of 2');
    }

    public function test_pupil_search_returns_matches_with_allowed_links(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);

        $this->actingAs($this->staff('Accountant'))
            ->getJson(route('search.pupils', ['q' => 'asan']))
            ->assertOk()
            ->assertJsonPath('0.name', 'ASANTE Kofi')
            ->assertJsonPath('0.links.fees', route('fees.show', $kofi))
            ->assertJsonMissingPath('0.links.report');

        $this->getJson(route('search.pupils', ['q' => $kofi->emergency_contact_phone]))->assertJsonCount(1);
        $this->getJson(route('search.pupils', ['q' => 'a']))->assertJsonCount(0);

        $this->actingAs($this->teacherUser())->getJson(route('search.pupils', ['q' => 'asan']))->assertForbidden();
    }

    /**
     * Every parameter-free page (plus pages for one pupil/class/subject) loads for the
     * Super Admin without a server error.
     */
    public function test_every_admin_page_loads(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();
        $this->actingAs($this->staff('Exams Officer'))->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [$kofi->id => ['class_score' => 30, 'exam_score' => 30]]]);
        $params = ['result' => \App\Models\Result::first()->id,'student' => $kofi->id, 'id' => $kofi->id, 'classGroup' => $this->basic4->id, 'class_group' => $this->basic4->id,
            'subject' => $subject->id, 'semester' => $this->term->id, 'teacher' => $this->teacher->id,
            'academicYear' => $this->term->academic_year_id, 'gradeScheme' => \App\Models\GradeScheme::first()->id];

        $this->actingAs($admin = $this->staff('Super Admin'));
        $params['user'] = $admin->id;
        $params['role'] = \Spatie\Permission\Models\Role::first()->id;
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!in_array('GET', $route->methods()) || preg_match('#^(_|up|storage|sanctum|password/reset/|student/|report-card/)|export|download|template|backup/|filter/|my-#', $uri)) {
                continue;
            }
            $missing = false;
            $url = '/' . preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($params, &$missing) {
                if (!isset($params[$m[1]])) {
                    $missing = true;

                    return '';
                }

                return $params[$m[1]];
            }, $uri);
            if ($missing) {
                continue;
            }

            $status = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($status >= 500 || $status === 404) {
                $failures[] = "$status $url";
            }
        }

        $this->assertSame([], $failures);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    public function test_both_sign_in_pages_render_and_link_to_each_other(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome back')->assertSee(route('student.login'));
        $this->get(route('student.login'))->assertOk()->assertSee('Admission number')->assertSee(route('login'));
    }

    public function test_staff_and_pupil_can_sign_in(): void
    {
        $this->buildSchool();
        $admin = $this->staff('Super Admin', ['email' => 'head@school.test']);

        $this->post(route('login'), ['email' => 'head@school.test', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        auth()->logout();

        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupilUser($pupil);
        $this->post(route('student.login'), ['index_number' => $pupil->index_number, 'password' => 'password'])
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_wrong_password_shows_an_error(): void
    {
        $this->post(route('login'), ['email' => 'nobody@school.test', 'password' => 'nope'])->assertSessionHasErrors();
        $this->from(route('login'))->followingRedirects()->post(route('login'), ['email' => 'nobody@school.test', 'password' => 'nope'])->assertSee('role="alert"', false);
    }
}

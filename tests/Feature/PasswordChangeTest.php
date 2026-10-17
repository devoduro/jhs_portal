<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function user(bool $firstLogin = false)
    {
        $user = $this->staff('Teacher', ['password' => Hash::make('Old-Pass1!'), 'first_login' => $firstLogin]);
        $this->actingAs($user);

        return $user;
    }

    public function test_page_loads_and_flags_a_first_login(): void
    {
        $this->user(true);
        $this->get(route('password.change'))->assertOk()->assertSee('First time signing in')->assertSee('Update Password');
    }

    public function test_changing_the_password_works_and_clears_the_first_login_flag(): void
    {
        $user = $this->user(true);

        $this->put(route('password.change.update'), ['current_password' => 'Old-Pass1!', 'password' => 'New-Pass2@', 'password_confirmation' => 'New-Pass2@'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('New-Pass2@', $user->fresh()->password));
        $this->assertFalse((bool) $user->fresh()->first_login);
    }

    public function test_wrong_current_weak_or_mismatched_passwords_are_refused(): void
    {
        $user = $this->user();

        $this->put(route('password.change.update'), ['current_password' => 'nope', 'password' => 'New-Pass2@', 'password_confirmation' => 'New-Pass2@'])->assertSessionHasErrors('current_password');
        $this->put(route('password.change.update'), ['current_password' => 'Old-Pass1!', 'password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->put(route('password.change.update'), ['current_password' => 'Old-Pass1!', 'password' => 'New-Pass2@', 'password_confirmation' => 'Different3#'])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), ['current_password' => 'Old-Pass1!', 'password' => 'Old-Pass1!', 'password_confirmation' => 'Old-Pass1!'])->assertSessionHasErrors('password');   // same as current

        $this->assertTrue(Hash::check('Old-Pass1!', $user->fresh()->password));
    }

    public function test_a_simple_password_is_accepted_and_temporary_passwords_are_six_digits(): void
    {
        $user = $this->user();

        // No symbols, capitals or numbers demanded - just 8 characters.
        $this->put(route('password.change.update'), ['current_password' => 'Old-Pass1!', 'password' => 'schoolbell', 'password_confirmation' => 'schoolbell'])->assertSessionDoesntHaveErrors();
        $this->assertTrue(Hash::check('schoolbell', $user->fresh()->password));

        foreach (range(1, 50) as $i) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', \App\Support\TemporaryPassword::make());
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('password.change'))->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

/** The staff "reset by SMS" flow, end to end through the real Speso client (its HTTP call faked). */
class PasswordResetViaSpesoTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        config(['services.sms_provider' => 'speso', 'services.speso_sms.api_key' => 'test-key', 'services.speso_sms.sender_id' => 'SCHOOL', 'services.speso_sms.base_url' => 'https://speso.test/api/v1']);
    }

    public function test_reset_link_is_texted_through_speso_and_works(): void
    {
        Http::fake(['speso.test/*' => Http::response(['success' => true, 'message' => 'Message sent successfully'], 201)]);
        $user = $this->staff('Super Admin', ['email' => 'head@school.test', 'phone' => '0242724849']);

        $this->from(route('password.request'))->post(route('password.email'), ['login' => '+233 24 272 4849'])->assertSessionHas('status');

        $link = null;
        Http::assertSent(function ($request) use (&$link) {
            $link = $request['message'];

            return $request->url() === 'https://speso.test/api/v1/messages/send'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['phone'] === '0242724849'
                && $request['sender_id'] === 'SCHOOL';
        });

        preg_match('#/password/reset/([A-Za-z0-9]+)\?email=#', $link, $m);
        $this->assertNotEmpty($m, 'the text should contain a reset link');

        $this->post(route('password.update'), ['token' => $m[1], 'email' => $user->email, 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
    }

    public function test_a_speso_refusal_is_shown_as_an_error_and_unknown_numbers_send_nothing(): void
    {
        Http::fake(['speso.test/*' => Http::response(['success' => false, 'message' => 'Insufficient SMS balance'], 402)]);
        $this->staff('Super Admin', ['phone' => '0242724849']);

        $this->from(route('password.request'))->post(route('password.email'), ['login' => '0242724849'])->assertSessionHas('error');

        Http::fake();   // anything else sent would show up below
        $this->from(route('password.request'))->post(route('password.email'), ['login' => '0559990000'])->assertSessionHas('status');
        Http::assertNothingSent();
    }
}

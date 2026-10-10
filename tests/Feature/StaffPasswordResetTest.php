<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Models\Teacher;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class StaffPasswordResetTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    /** @var array<int, array{0: string, 1: string}> SMS "sent" during the test: [phone, message] */
    private array $sent = [];
    private bool $smsWorks = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $fake = new class($this) extends PastechSmsService {
            public function __construct(private StaffPasswordResetTest $test) {}
            public function sendSms(string $phone, string $message): array
            {
                return $this->test->recordSms($phone, $message);
            }
        };
        $this->app->instance(PastechSmsService::class, $fake);
    }

    public function recordSms(string $phone, string $message): array
    {
        $this->sent[] = [$phone, $message];

        return $this->smsWorks ? ['success' => true, 'code' => '1000', 'message' => 'ok'] : ['success' => false, 'code' => '1003', 'message' => 'Insufficient balance'];
    }

    private function requestReset(string $login)
    {
        return $this->from(route('password.request'))->post(route('password.email'), ['login' => $login]);
    }

    public function test_request_page_offers_email_or_phone(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Email address or mobile number');
        $this->get(route('login'))->assertSee(route('password.request'));
    }

    public function test_email_sends_reset_link_email(): void
    {
        Notification::fake();
        $user = $this->staff('Accountant', ['email' => 'bursar@school.test']);

        $this->requestReset('bursar@school.test')->assertSessionHas('status', ForgotPasswordController::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertSame([], $this->sent);
    }

    public function test_phone_in_any_format_sends_sms_link_that_resets_the_password(): void
    {
        $user = $this->staff('Super Admin', ['email' => 'head@school.test', 'phone' => '024 123 4567']);

        $this->requestReset('+233241234567')->assertSessionHas('status', ForgotPasswordController::GENERIC_STATUS);

        $this->assertCount(1, $this->sent);
        [$to, $message] = $this->sent[0];
        $this->assertSame('024 123 4567', $to);
        $this->assertMatchesRegularExpression('#/password/reset/([A-Za-z0-9]+)\?email=#', $message);

        preg_match('#/password/reset/([A-Za-z0-9]+)\?#', $message, $m);
        $this->get(route('password.reset', ['token' => $m[1], 'email' => $user->email]))->assertOk()->assertSee('Set a new password');

        $this->post(route('password.update'), [
            'token' => $m[1], 'email' => $user->email, 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->post(route('login'), ['email' => 'head@school.test', 'password' => 'NewSecret123']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_teacher_phone_is_used_when_the_account_has_none(): void
    {
        $teacher = Teacher::create(['name' => 'Ama Owusu', 'email' => 'ama@school.test', 'phone' => '0201112233']);
        $this->staff('Teacher', ['email' => 'ama@school.test', 'teacher_id' => $teacher->id]);

        $this->requestReset('020-111-2233');

        $this->assertCount(1, $this->sent);
        $this->assertSame('0201112233', $this->sent[0][0]);
    }

    public function test_unknown_details_give_the_same_answer_and_send_nothing(): void
    {
        Notification::fake();

        $this->requestReset('nobody@school.test')->assertSessionHas('status', ForgotPasswordController::GENERIC_STATUS);
        $this->requestReset('0559999999')->assertSessionHas('status', ForgotPasswordController::GENERIC_STATUS);

        Notification::assertNothingSent();
        $this->assertSame([], $this->sent);
    }

    public function test_pupil_accounts_cannot_use_the_staff_form(): void
    {
        $this->buildSchool();
        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $user = $this->pupilUser($pupil);
        $user->update(['phone' => '0247777777']);

        $this->requestReset('0247777777');

        $this->assertSame([], $this->sent);
    }

    public function test_failed_sms_and_bad_input_are_reported(): void
    {
        $this->staff('Super Admin', ['phone' => '0241234567']);
        $this->smsWorks = false;

        $this->requestReset('0241234567')->assertSessionHas('error');
        $this->requestReset('hello')->assertSessionHasErrors('login');
        $this->requestReset('')->assertSessionHasErrors('login');
    }

    public function test_staff_can_add_their_phone_on_their_profile(): void
    {
        $user = $this->staff('Teacher');

        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '0501234567'])
            ->assertRedirect(route('profile'));

        $this->assertSame('0501234567', $user->fresh()->phone);
        $this->get(route('profile'))->assertOk()->assertSee('0501234567');
    }
}

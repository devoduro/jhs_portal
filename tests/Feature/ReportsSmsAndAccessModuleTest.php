<?php

namespace Tests\Feature;

use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Subject;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ReportsSmsAndAccessModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    // ------------------------------------------------------------ reports page

    public function test_performance_report_summarises_each_class_and_subject(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $subject = Subject::where('level', 9)->first();

        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [
            $kofi->id => ['class_score' => 40, 'exam_score' => 40], $akua->id => ['class_score' => 20, 'exam_score' => 20],
        ]]);

        $this->get(route('reports.index'))->assertOk()
            ->assertViewHas('classes', function ($classes) use ($subject) {
                $row = $classes->firstWhere('class.name', 'Basic 4A');

                return $row['roll'] === 2 && $row['scored'] === 2 && abs($row['average'] - 60) < 0.01 && $row['passRate'] == 100 + 0 - 50
                    && $row['top']->first()['student']->full_name === 'ASANTE Kofi'
                    && $row['subjects']->first()['subject']->id === $subject->id;
            });
        $this->get(route('reports.index', ['semester_id' => $this->term->id]))->assertOk();
    }

    public function test_reports_need_the_view_reports_permission(): void
    {
        $this->actingAs($this->staff('Head Teacher'))->get(route('reports.index'))->assertOk();
        $this->actingAs($this->teacherUser())->get(route('reports.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------- sms

    public function test_sms_history_templates_and_automatic_log_screens(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $campaign = SmsCampaign::create(['message' => 'Hello parents', 'filters' => [], 'total_recipients' => 1, 'success_count' => 1, 'failed_count' => 0, 'sent_by' => auth()->id()]);
        SmsLog::create(['sms_campaign_id' => $campaign->id, 'type' => SmsLog::TYPE_CAMPAIGN, 'student_id' => $kofi->id, 'phone' => '0244000001', 'message' => 'Hello parents', 'success' => true]);
        SmsLog::create(['type' => SmsLog::TYPE_FEES, 'student_id' => $kofi->id, 'phone' => '0244000001', 'message' => 'Fee receipt', 'success' => false]);

        $this->get(route('sms.index'))->assertOk();
        $this->get(route('sms.history'))->assertOk()->assertSee('Hello parents');
        $this->get(route('sms.history.show', $campaign))->assertOk()->assertSee('0244000001');
        $this->get(route('sms.parent-notifications'))->assertOk()->assertSee('Fee receipt');
        $this->get(route('sms.parent-notifications', ['status' => 'sent']))->assertOk()->assertViewHas('logs', fn ($l) => $l->count() === 0);
        $this->get(route('sms.parent-notifications', ['status' => 'failed']))->assertViewHas('logs', fn ($l) => $l->count() === 1);
        $this->get(route('sms.parent-notifications', ['type' => 'fees', 'class_group_id' => $this->basic4->id]))->assertOk()->assertSee('Fee receipt');

        $before = SmsTemplate::count();
        $this->get(route('sms.templates.index'))->assertOk();
        $this->post(route('sms.templates.store'), ['name' => '', 'message' => 'x'])->assertSessionHasErrors('name');
        $this->post(route('sms.templates.store'), ['name' => 'Reminder', 'message' => str_repeat('x', 700)])->assertSessionHasErrors('message');
        $this->post(route('sms.templates.store'), ['name' => 'Reminder', 'message' => 'Dear {parent}, {pupil} owes {balance}.'])->assertRedirect(route('sms.templates.index'));

        $template = SmsTemplate::where('name', 'Reminder')->first();
        $this->put(route('sms.templates.update', $template), ['name' => 'Reminder 2', 'message' => 'Updated'])->assertRedirect();
        $this->assertSame('Reminder 2', $template->fresh()->name);
        $this->delete(route('sms.templates.destroy', $template))->assertRedirect();
        $this->assertSame($before, SmsTemplate::count());
    }

    public function test_gateway_reports_success_failure_and_outages_and_cleans_numbers(): void
    {
        config(['services.pastech_sms' => ['api_key' => 'k', 'sender_id' => 'SCH', 'endpoint' => 'https://sms.test/send', 'balance_endpoint' => 'https://sms.test/balance']]);
        $sms = new PastechSmsService();

        $this->assertSame('0241234567', $sms->normalizePhone('233241234567'));
        $this->assertSame('0241234567', $sms->normalizePhone('0241234567'));

        Http::fakeSequence('sms.test/send*')->push('1000')->push('1003')->push('Error: 1005 invalid')->push('<html>gateway down</html>', 500);
        $this->assertTrue($sms->sendSms('0241234567', 'hi')['success']);
        $broke = $sms->sendSms('0241234567', 'hi');
        $this->assertFalse($broke['success']);
        $this->assertSame('Insufficient balance', $broke['message']);
        $this->assertSame('1005', $sms->sendSms('0241234567', 'hi')['code']);
        $this->assertFalse($sms->sendSms('0241234567', 'hi')['success']);

    }

    public function test_gateway_request_carries_the_key_sender_and_a_cleaned_number(): void
    {
        config(['services.pastech_sms' => ['api_key' => 'k', 'sender_id' => 'SCH', 'endpoint' => 'https://sms.test/send', 'balance_endpoint' => 'https://sms.test/balance']]);
        Http::fake(['sms.test/*' => Http::response('1000')]);

        (new PastechSmsService())->sendSms('+233 24 123 4567', 'hello');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'to=0241234567') && str_contains($request->url(), 'msg=hello')
            && str_contains($request->url(), 'sender_id=SCH') && str_contains($request->url(), 'key=k'));
    }

    // ----------------------------------------------------------------- access

    public function test_new_staff_must_choose_their_own_password_before_anything_else(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'first_login' => true, 'password' => Hash::make('Temp#Pass1')]);
        $user->assignRole('Accountant');
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('fees.index'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();                                   // the way out stays open

        $this->put(route('password.change.update'), ['current_password' => 'wrong', 'password' => 'Brand#New99x', 'password_confirmation' => 'Brand#New99x'])->assertSessionHasErrors('current_password');
        $this->put(route('password.change.update'), ['current_password' => 'Temp#Pass1', 'password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->put(route('password.change.update'), ['current_password' => 'Temp#Pass1', 'password' => 'Brand#New99x', 'password_confirmation' => 'Brand#New99x'])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('Brand#New99x', $user->password));
        $this->assertEquals(0, $user->first_login);
        $this->get(route('fees.index'))->assertOk();
    }

    public function test_pupil_search_requires_a_pupil_related_permission(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);

        $this->actingAs($this->staff('Accountant'))->getJson(route('search.pupils', ['q' => 'Asante']))->assertOk()->assertJsonPath('0.name', 'ASANTE Kofi');
        $this->getJson(route('search.pupils', ['q' => 'A']))->assertOk()->assertJsonCount(0);        // too short to search

        $this->actingAs($this->teacherUser())->getJson(route('search.pupils', ['q' => 'Asante']))->assertForbidden();
    }

    public function test_guests_are_sent_to_sign_in_and_signing_out_ends_the_session(): void
    {
        foreach ([route('dashboard'), route('students.index'), route('fees.index'), route('settings.index'), route('class-lists.index')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs($this->staff('Super Admin'))->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_repeated_wrong_passwords_lock_a_staff_sign_in_out_for_a_while(): void
    {
        $this->staff('Accountant', ['email' => 'bursar@example.test', 'password' => Hash::make('Right#Pass1')]);
        auth()->logout();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'bursar@example.test', 'password' => 'wrong-' . $i])->assertSessionHasErrors('email');
        }

        // Locked: even the right password is refused, with a message that says why.
        $this->post(route('login'), ['email' => 'bursar@example.test', 'password' => 'Right#Pass1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many', session('errors')->first('email'));

        // Someone else's address from the same place is not locked out.
        $this->staff('Teacher', ['email' => 'other@example.test', 'password' => Hash::make('Other#Pass1')]);
        auth()->logout();
        $this->post(route('login'), ['email' => 'other@example.test', 'password' => 'Other#Pass1'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_a_successful_sign_in_resets_the_failed_attempt_count(): void
    {
        $this->staff('Accountant', ['email' => 'bursar@example.test', 'password' => Hash::make('Right#Pass1')]);
        auth()->logout();

        for ($round = 0; $round < 3; $round++) {
            for ($i = 0; $i < 4; $i++) {
                $this->post(route('login'), ['email' => 'bursar@example.test', 'password' => 'wrong']);
            }
            $this->post(route('login'), ['email' => 'bursar@example.test', 'password' => 'Right#Pass1'])->assertRedirect();
            $this->assertAuthenticated();
            auth()->logout();
        }
    }

    public function test_repeated_wrong_passwords_also_lock_a_pupil_sign_in(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupilUser($kofi)->update(['password' => Hash::make('pupilpass1')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('student.login'), ['index_number' => $kofi->index_number, 'password' => 'wrong']);
        }

        $this->post(route('student.login'), ['index_number' => $kofi->index_number, 'password' => 'pupilpass1'])->assertSessionHasErrors('index_number');
        $this->assertGuest();
        $this->assertStringContainsString('Too many', session('errors')->first('index_number'));
    }
}

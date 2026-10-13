<?php

namespace Tests\Feature;

use App\Mail\ParentFeesMail;
use App\Mail\ParentResultsMail;
use App\Models\EmailLog;
use App\Models\SmsLog;
use App\Models\Subject;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ParentNoticesTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private array $texts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        Mail::fake();

        $sms = \Mockery::mock(PastechSmsService::class);
        $sms->shouldReceive('sendSms')->andReturnUsing(function ($phone, $message) {
            $this->texts[] = [$phone, $message];

            return ['success' => true, 'code' => '1000', 'message' => 'ok'];
        });
        $this->app->instance(PastechSmsService::class, $sms);
    }

    /** Kofi (phone + email) and Akua (phone only), both scored and billed; Esi (no contact at all). */
    private function setUpClass(): array
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4, 'Male', ['parent_email' => 'kofi.parent@example.test']);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $esi = $this->pupil('ADJEI Esi', $this->basic4, 'Female', ['emergency_contact_phone' => null]);

        $admin = $this->staff('Super Admin');
        $this->actingAs($admin);
        $subject = Subject::where('level', 9)->first();
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [
            $kofi->id => ['class_score' => 40, 'exam_score' => 40], $akua->id => ['class_score' => 30, 'exam_score' => 30], $esi->id => ['class_score' => 20, 'exam_score' => 20],
        ]]);
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1000]]]);
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
        $this->post(route('fees.payments.store', $kofi), ['semester_id' => $this->term->id, 'amount' => 400, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);

        return [$kofi, $akua, $esi];
    }

    public function test_results_go_to_each_parent_by_sms_and_email(): void
    {
        [$kofi, $akua, $esi] = $this->setUpClass();

        $this->post(route('parent-notices.send'), [
            'semester_id' => $this->term->id, 'class_group_ids' => [$this->basic4->id], 'kinds' => ['results'], 'channels' => ['sms', 'email'],
        ])->assertRedirect()->assertSessionHas('success');

        // Texts: Kofi and Akua have phones; Esi has none.
        $this->assertCount(2, $this->texts);
        $this->assertSame(2, SmsLog::where('type', SmsLog::TYPE_GRADES)->count());

        // Email: only Kofi has a parent email, and it carries his own scores and a signed link.
        Mail::assertSent(ParentResultsMail::class, 1);
        Mail::assertSent(ParentResultsMail::class, function (ParentResultsMail $mail) use ($kofi) {
            return $mail->hasTo('kofi.parent@example.test') && $mail->student->id === $kofi->id && str_contains($mail->link, 'signature=');
        });
        $this->assertSame(1, EmailLog::where('type', 'results')->where('success', true)->count());
    }

    public function test_results_email_renders_scores_and_position(): void
    {
        [$kofi] = $this->setUpClass();
        $this->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'student_ids' => [$kofi->id], 'kinds' => ['results'], 'channels' => ['email']]);

        Mail::assertSent(ParentResultsMail::class, function (ParentResultsMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'ASANTE Kofi') && str_contains($html, '1st out of 3') && str_contains($html, 'View / Print Full Report Card');
        });
    }

    public function test_fee_statements_show_the_pupils_own_balance_and_can_be_limited_to_those_owing(): void
    {
        [$kofi, $akua] = $this->setUpClass();     // Kofi owes 600; Akua owes 1000
        $this->post(route('fees.payments.store', $akua), ['semester_id' => $this->term->id, 'amount' => 1000, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);
        $akua->update(['parent_email' => 'akua.parent@example.test']);

        $this->post(route('parent-notices.send'), [
            'semester_id' => $this->term->id, 'class_group_ids' => [$this->basic4->id], 'kinds' => ['fees'], 'channels' => ['email', 'sms'], 'only_owing' => 1,
        ])->assertSessionHas('success');

        // Akua has cleared her fees, so only Kofi (who still owes) is contacted.
        Mail::assertSent(ParentFeesMail::class, 1);
        Mail::assertSent(ParentFeesMail::class, fn (ParentFeesMail $m) => $m->hasTo('kofi.parent@example.test') && str_contains($m->render(), 'GHS 600.00'));
        $this->assertCount(1, $this->texts);
        $this->assertStringContainsString('Total owing: GHS 600.00', $this->texts[0][1]);
        $this->assertStringContainsString('ASANTE Kofi', $this->texts[0][1]);
    }

    public function test_single_pupil_send_from_their_account_and_validation(): void
    {
        [$kofi] = $this->setUpClass();

        $this->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'student_ids' => [$kofi->id], 'kinds' => ['fees'], 'channels' => ['email']])->assertSessionHas('success');
        Mail::assertSent(ParentFeesMail::class, 1);

        $this->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'kinds' => ['fees'], 'channels' => ['email']])->assertSessionHas('error');   // nobody chosen
        $this->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'whole_school' => 1, 'kinds' => [], 'channels' => ['email']])->assertSessionHasErrors('kinds');
        $this->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'whole_school' => 1, 'kinds' => ['fees'], 'channels' => []])->assertSessionHasErrors('channels');
    }

    public function test_pupil_with_no_scores_gets_no_results_message(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4, 'Male', ['parent_email' => 'kofi.parent@example.test']);
        $this->actingAs($this->staff('Super Admin'))
            ->post(route('parent-notices.send'), ['semester_id' => $this->term->id, 'student_ids' => [$kofi->id], 'kinds' => ['results'], 'channels' => ['sms', 'email']])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertCount(0, $this->texts);
    }

    public function test_page_renders_parent_email_is_saved_and_access_is_permissioned(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->get(route('parent-notices.index'))->assertOk()->assertSee('Send to parents');

        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->put(route('students.update', $kofi), ['full_name' => 'ASANTE Kofi', 'gender' => 'Male', 'class_group_id' => $this->basic4->id, 'parent_email' => 'dad@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('dad@example.test', $kofi->fresh()->parent_email);
        $this->get(route('fees.show', $kofi))->assertOk()->assertSee('fee statement to parent');

        $this->actingAs($this->teacherUser())->get(route('parent-notices.index'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class BulkSmsTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    /** @var array<int, array{0: string, 1: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();

        $test = $this;
        $this->app->instance(PastechSmsService::class, new class($test) extends PastechSmsService {
            public function __construct(private BulkSmsTest $test) {}
            public function sendSms(string $phone, string $message): array
            {
                return $this->test->record($phone, $message);
            }
        });

        $this->actingAs($this->staff('Super Admin'));
    }

    public function record(string $phone, string $message): array
    {
        $this->sent[] = [$phone, $message];

        return str_starts_with($phone, '0559') ? ['success' => false, 'code' => '1005', 'message' => 'Invalid phone number'] : ['success' => true, 'code' => '1000', 'message' => 'ok'];
    }

    public function test_page_shows_parents_classes_and_templates(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);

        $this->get(route('sms.index'))
            ->assertOk()
            ->assertSee('Parents &amp; guardians', false)
            ->assertSee('Basic 4A')
            ->assertSee('Fees reminder')
            ->assertSee('{balance}')
            ->assertSee('Parent of ASANTE Kofi');

        $this->get(route('sms.index', ['audience' => 'staff']))->assertOk()->assertSee('All staff');
    }

    public function test_personalised_fee_reminder_goes_only_to_parents_who_owe(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->post(route('fees.charges.store', $kofi), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 450]);

        $this->post(route('sms.send'), [
            'fees' => 'owing',
            'message' => 'Dear {parent}, {first_name} in {class} owes {balance}. - {school}',
        ])->assertRedirect();

        $this->assertCount(1, $this->sent);
        [$phone, $message] = $this->sent[0];
        $this->assertSame($kofi->emergency_contact_phone, $phone);
        $this->assertSame('Dear Parent of ASANTE Kofi, Kofi in Basic 4A owes GHS 450.00. - ' . \App\Models\Setting::get('institution_name', 'the school'), $message);
        $this->assertSame(1, SmsLog::where('student_id', $kofi->id)->count());
        $this->assertSame(0, SmsLog::where('student_id', $akua->id)->count());
    }

    public function test_general_message_reaches_a_family_once_but_personal_message_once_per_child(): void
    {
        $this->pupil('MENSAH Yaw', $this->basic4, 'Male', ['emergency_contact_phone' => '0241110000']);
        $this->pupil('MENSAH Esi', $this->jhs1, 'Female', ['emergency_contact_phone' => '024 111 0000']);

        $this->post(route('sms.send'), ['message' => 'PTA meeting on Saturday. - {school}']);
        $this->assertCount(1, $this->sent);

        $this->sent = [];
        $this->post(route('sms.send'), ['message' => 'Dear parent, {first_name} reopens Monday.']);
        $this->assertCount(2, $this->sent);
    }

    public function test_class_filter_and_campaign_record(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->pupil('NO PHONE Pupil', $this->jhs1, 'Male', ['emergency_contact_phone' => null]);

        $response = $this->post(route('sms.send'), ['class_group_ids' => [$this->jhs1->id], 'message' => 'JHS 1 notice']);

        $campaign = SmsCampaign::first();
        $response->assertRedirect(route('sms.history.show', $campaign));
        $this->assertSame(1, $campaign->total_recipients);
        $this->assertSame([$this->jhs1->id], array_map('intval', $campaign->filters['class_group_ids']));
        $this->get(route('sms.history.show', $campaign))->assertOk()->assertSee('ADJEI Esi');
    }

    public function test_staff_audience_uses_staff_phones(): void
    {
        $this->staff('Teacher', ['name' => 'Mr. Kwame Boateng', 'phone' => '0201234567']);
        $this->staff('Accountant', ['name' => 'No Phone']);

        $this->post(route('sms.send'), ['audience' => 'staff', 'message' => 'Dear {name}, staff meeting at 2pm.']);

        $this->assertCount(1, $this->sent);
        $this->assertSame('Dear Mr. Kwame Boateng, staff meeting at 2pm.', $this->sent[0][1]);
    }

    public function test_failures_are_counted_and_empty_audiences_rejected(): void
    {
        $this->pupil('BAD Number', $this->basic4, 'Male', ['emergency_contact_phone' => '0559999999']);

        $this->post(route('sms.send'), ['message' => 'Hello'])->assertSessionHas('error');
        $this->assertSame(1, SmsCampaign::first()->failed_count);

        $this->post(route('sms.send'), ['message' => 'Hello', 'search' => 'nobody-matches'])->assertSessionHas('error');
        $this->post(route('sms.send'), ['message' => ''])->assertSessionHasErrors('message');
    }

    public function test_teachers_cannot_send_bulk_sms(): void
    {
        $this->actingAs($this->teacherUser())->get(route('sms.index'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    public function test_changes_made_by_staff_are_logged_with_who_what_and_detail(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $bursar = $this->staff('Accountant', ['name' => 'Ben Bursar']);

        $this->actingAs($bursar)->post(route('fees.payments.store', $kofi), [
            'semester_id' => $this->term->id, 'amount' => 250, 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'payment')->first();
        $this->assertNotNull($log);
        $this->assertSame($bursar->id, $log->user_id);
        $this->assertStringContainsString('ASANTE Kofi', $log->description);
        $this->assertStringContainsString('250.00', $log->description);
        $this->assertSame('fees.payments.store', $log->route_name);

        // Reading pages, or a submission that fails validation, is not an activity.
        $count = ActivityLog::count();
        $this->get(route('fees.index'));
        $this->post(route('fees.payments.store', $kofi), ['amount' => -5, 'semester_id' => $this->term->id, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);
        $this->assertSame($count, ActivityLog::count());
    }

    public function test_page_filters_by_person_action_text_and_date_and_exports_csv(): void
    {
        $admin = $this->staff('Super Admin', ['name' => 'Ann Admin']);
        $other = $this->staff('Accountant', ['name' => 'Ben Bursar']);
        ActivityLog::create(['user_id' => $admin->id, 'user_type' => get_class($admin), 'action' => 'login', 'description' => 'Admin logged in successfully', 'ip_address' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537']);
        ActivityLog::create(['user_id' => $other->id, 'user_type' => get_class($other), 'action' => 'payment', 'description' => 'Added payment: ODOOM Yaa (GH₵ 90.00)', 'subject' => 'ODOOM Yaa']);
        $old = ActivityLog::create(['user_id' => $other->id, 'user_type' => get_class($other), 'action' => 'billing', 'description' => 'Saved fee schedule']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->actingAs($admin);
        $this->get(route('activity-logs.index'))->assertOk()->assertSee('Chrome on Windows')->assertSee('ODOOM Yaa');
        $this->get(route('activity-logs.index', ['q' => 'ODOOM']))->assertSee('ODOOM Yaa')->assertDontSee('Admin logged in');
        $this->get(route('activity-logs.index', ['action' => 'login']))->assertSee('Admin logged in')->assertDontSee('ODOOM Yaa');
        $this->get(route('activity-logs.index', ['action' => 'changes']))->assertSee('ODOOM Yaa')->assertDontSee('Admin logged in');
        $this->get(route('activity-logs.index', ['user_id' => $admin->id]))->assertDontSee('ODOOM Yaa');
        $this->get(route('activity-logs.index', ['from' => now()->subDays(3)->toDateString()]))->assertDontSee('Saved fee schedule')->assertSee('ODOOM Yaa');

        $csv = $this->get(route('activity-logs.export', ['action' => 'payment']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('ODOOM Yaa', $body);
        $this->assertStringNotContainsString('Saved fee schedule', $body);
    }

    public function test_only_users_with_the_permission_can_see_the_log(): void
    {
        $this->actingAs($this->teacherUser())->get(route('activity-logs.index'))->assertForbidden();
        $this->get(route('activity-logs.export'))->assertForbidden();
    }
}

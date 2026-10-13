<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class AddTeacherTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Super Admin'));

        $sms = \Mockery::mock(PastechSmsService::class);
        $sms->shouldReceive('sendSms')->andReturn(['success' => true, 'message' => 'ok']);
        $this->app->instance(PastechSmsService::class, $sms);
    }

    public function test_add_page_suggests_a_staff_id_and_lists_free_classes_and_subjects(): void
    {
        $this->get(route('teachers.create'))->assertOk()
            ->assertSee('Teaching')->assertSee('TCH001')                       // the pattern starts at 001
            ->assertSee('JHS 1 Gold')->assertDontSee('Basic 4A');              // Basic 4A already has Ama as class teacher
    }

    public function test_adding_a_teacher_assigns_class_subjects_and_login(): void
    {
        $subject = Subject::where('level', 12)->first();

        $this->post(route('teachers.store'), [
            'name' => 'Mr. Kojo Badu', 'email' => 'kojo@example.test', 'phone' => '+233 24 555 1212',
            'class_group_ids' => [$this->jhs1->id], 'subject_ids' => [$subject->id], 'create_login' => 1, 'send_sms' => 1,
        ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Temporary password') && str_contains($m, 'TCH001'));

        $teacher = Teacher::where('email', 'kojo@example.test')->first();
        $this->assertSame('0245551212', $teacher->phone);
        $this->assertSame('TCH001', $teacher->staff_id);
        $this->assertSame($teacher->id, $this->jhs1->fresh()->class_teacher_id);
        $this->assertTrue($teacher->subjects->contains($subject));
        $this->assertTrue(User::where('teacher_id', $teacher->id)->first()->hasRole('Teacher'));
    }

    public function test_a_class_that_already_has_a_class_teacher_cannot_be_taken_over(): void
    {
        $this->post(route('teachers.store'), ['name' => 'Mr. Kojo Badu', 'class_group_ids' => [$this->basic4->id]]);

        $this->assertSame($this->teacher->id, $this->basic4->fresh()->class_teacher_id);
    }

    public function test_login_is_skipped_without_an_email_and_edit_updates_assignments(): void
    {
        $this->post(route('teachers.store'), ['name' => 'Mr. Kojo Badu', 'create_login' => 1])->assertSessionHas('success', fn ($m) => str_contains($m, 'No login was created'));
        $this->assertSame(0, User::whereNotNull('teacher_id')->count());

        $subject = Subject::where('level', 9)->first();
        $this->get(route('teachers.edit', $this->teacher))->assertOk()->assertSee('Basic 4A');   // their own class stays listed
        $this->put(route('teachers.update', $this->teacher), ['name' => 'Ama Owusu', 'assignments_submitted' => 1, 'subject_ids' => [$subject->id]])->assertRedirect();

        $this->assertNull($this->basic4->fresh()->class_teacher_id);                              // unticked = released
        $this->assertTrue($this->teacher->fresh()->subjects->contains($subject));
    }

    public function test_duplicate_lookup_matches_email_phone_and_name(): void
    {
        Teacher::create(['name' => 'Grace Owusu Mensah', 'email' => 'grace@example.test', 'phone' => '0244111222', 'staff_id' => 'TCH050']);

        $this->getJson(route('teachers.duplicates', ['email' => 'grace@example.test']))->assertJsonPath('0.why', 'same email');
        $this->getJson(route('teachers.duplicates', ['phone' => '+233 24 411 1222']))->assertJsonPath('0.why', 'same phone');
        $this->getJson(route('teachers.duplicates', ['name' => 'Grace Owusu']))->assertJsonPath('0.why', 'similar name');
        $this->getJson(route('teachers.duplicates', ['name' => 'Grace']))->assertJsonCount(0);
    }
}

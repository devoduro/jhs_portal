<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class AdmitPupilTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Super Admin'));
    }

    public function test_admit_page_shows_class_roll_and_fees(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1200]]]);

        $this->get(route('students.create'))->assertOk()->assertSee('Class placement')
            ->assertViewHas('classInfo', fn ($i) => $i[$this->basic4->id]['roll'] === 1 && $i[$this->basic4->id]['fees'] == 1200);
    }

    public function test_admitting_normalises_phone_and_can_bill_the_term_straight_away(): void
    {
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1200], 'feeding' => [9 => 300]]]);

        $this->post(route('students.store'), [
            'full_name' => 'MENSAH Yaw', 'gender' => 'Male', 'class_group_id' => $this->basic4->id,
            'emergency_contact_name' => 'Mrs Mensah', 'emergency_contact_phone' => '+233 24 123 4567', 'parent_email' => 'mum@example.test', 'bill_now' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $pupil = Student::where('full_name', 'MENSAH Yaw')->first();
        $this->assertSame('0241234567', $pupil->emergency_contact_phone);
        $this->assertEquals(1500, $pupil->billedAmount());

        // Without the tick, nothing is billed.
        $this->post(route('students.store'), ['full_name' => 'OSEI Ama', 'gender' => 'Female', 'class_group_id' => $this->basic4->id, 'emergency_contact_phone' => '24 765 4321']);
        $ama = Student::where('full_name', 'OSEI Ama')->first();
        $this->assertSame('0247654321', $ama->emergency_contact_phone);
        $this->assertEquals(0, $ama->billedAmount());
    }

    public function test_duplicate_and_family_lookups(): void
    {
        $this->pupil('MENSAH Kofi Yaw', $this->basic4, 'Male', ['emergency_contact_name' => 'Mrs Mensah', 'emergency_contact_phone' => '0244000111', 'parent_email' => 'mum@example.test']);

        $this->getJson(route('students.duplicates', ['name' => 'Mensah Kofi']))->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'MENSAH Kofi Yaw');
        $this->getJson(route('students.duplicates', ['name' => 'Mensah']))->assertOk()->assertJsonCount(0);          // one word is too vague
        $this->getJson(route('students.duplicates', ['name' => 'Boateng Akua']))->assertJsonCount(0);

        $this->getJson(route('students.family', ['q' => '0244000111']))->assertOk()->assertJsonPath('0.parent.emergency_contact_name', 'Mrs Mensah')->assertJsonPath('0.parent.parent_email', 'mum@example.test');
    }

    public function test_lookups_need_the_manage_pupils_permission(): void
    {
        $this->actingAs($this->staff('Accountant'));
        $this->getJson(route('students.duplicates', ['name' => 'Mensah Kofi']))->assertForbidden();
        $this->getJson(route('students.family', ['q' => 'Mensah']))->assertForbidden();
    }

    public function test_edit_page_still_renders(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->get(route('students.edit', $kofi))->assertOk()->assertSee('Class placement')->assertSee('ASANTE Kofi');
    }
}

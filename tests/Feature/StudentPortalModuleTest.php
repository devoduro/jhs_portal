<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class StudentPortalModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private Student $kofi;
    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->kofi = $this->pupil('ASANTE Kofi', $this->basic4, 'Male', ['date_of_birth' => '2016-03-09', 'emergency_contact_phone' => '0244111222', 'parent_email' => 'mum@example.test']);
        $this->login = $this->pupilUser($this->kofi);
        $this->login->update(['password' => Hash::make('pupilpass1')]);
    }

    public function test_pupil_signs_in_with_admission_number_and_wrong_credentials_are_refused(): void
    {
        $this->get(route('student.login'))->assertOk();

        $this->post(route('student.login'), ['index_number' => $this->kofi->index_number, 'password' => 'wrong'])->assertSessionHasErrors();
        $this->post(route('student.login'), ['index_number' => 'NOPE/1', 'password' => 'pupilpass1'])->assertSessionHasErrors('index_number');
        $this->post(route('student.login'), [])->assertSessionHasErrors(['index_number', 'password']);
        $this->assertGuest();

        $this->post(route('student.login'), ['index_number' => $this->kofi->index_number, 'password' => 'pupilpass1'])->assertRedirect();
        $this->assertAuthenticatedAs($this->login);

        $this->post(route('student.logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_pupil_screens_load_and_show_their_own_data(): void
    {
        $this->actingAs($this->login);
        $this->post(route('fee-structures.save'), []);   // (forbidden for a pupil - nothing happens)
        StudentFeeCharge::create(['student_id' => $this->kofi->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 800]);
        TimetableEntry::create(['class_group_id' => $this->basic4->id, 'subject_id' => Subject::where('level', 9)->first()->id, 'semester_id' => $this->term->id, 'day_of_week' => now()->dayOfWeek, 'start_time' => '08:00', 'end_time' => '09:00']);

        $this->get(route('student.dashboard'))->assertOk()->assertSee('ASANTE Kofi');
        $this->get(route('student.fees.index'))->assertOk()->assertSee('800.00');
        $this->get(route('student.timetable'))->assertOk();
        $this->get(route('student.timetable.print'))->assertOk();
        $this->get(route('student.profile.edit'))->assertOk();
        $this->get(route('student.change-password'))->assertOk();
        $this->get(route('student.quizzes.index'))->assertOk();
        $this->get(route('student.assignments.index'))->assertOk();
        $this->get(route('student.report-card'))->assertStatus(200);
    }

    public function test_pupils_are_kept_out_of_staff_screens_and_staff_out_of_the_pupil_area(): void
    {
        $this->actingAs($this->login);
        foreach ([route('students.index'), route('fees.index'), route('settings.index'), route('users.index'), route('scores.index')] as $url) {
            $this->assertContains($this->get($url)->getStatusCode(), [302, 403], $url);
        }

        $this->actingAs($this->staff('Super Admin'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('student.fees.index'))->assertRedirect(route('login'));
    }

    public function test_first_login_forces_a_password_change_and_then_lets_the_pupil_in(): void
    {
        $this->login->update(['first_login' => true]);
        $this->actingAs($this->login);

        // Until they choose their own password, every pupil page sends them to the change-password form.
        $this->get(route('student.dashboard'))->assertRedirect(route('student.change-password'));
        $this->get(route('student.fees.index'))->assertRedirect(route('student.change-password'));
        $this->get(route('student.change-password'))->assertOk();

        $this->post(route('student.update-password'), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post(route('student.update-password'), ['password' => 'newpupilpass', 'password_confirmation' => 'newpupilpass'])->assertRedirect(route('student.dashboard'));

        $this->login->refresh();
        $this->assertTrue(Hash::check('newpupilpass', $this->login->password));
        $this->assertEquals(0, $this->login->first_login);
        $this->get(route('student.dashboard'))->assertOk();

        // Afterwards a change needs the current password.
        $this->post(route('student.update-password'), ['password' => 'anotherpass1', 'password_confirmation' => 'anotherpass1'])->assertSessionHasErrors('current_password');
        $this->post(route('student.update-password'), ['current_password' => 'wrong', 'password' => 'anotherpass1', 'password_confirmation' => 'anotherpass1'])->assertSessionHasErrors('current_password');
        $this->post(route('student.update-password'), ['current_password' => 'newpupilpass', 'password' => 'anotherpass1', 'password_confirmation' => 'anotherpass1'])->assertRedirect();
        $this->assertTrue(Hash::check('anotherpass1', $this->login->fresh()->password));
    }

    public function test_pupil_updates_contacts_but_cannot_change_a_recorded_date_of_birth(): void
    {
        \Storage::fake('public');
        $this->actingAs($this->login);

        $this->post(route('student.profile.update'), ['emergency_contact_name' => '', 'emergency_contact_phone' => ''])->assertSessionHasErrors(['emergency_contact_name', 'emergency_contact_phone']);

        $this->post(route('student.profile.update'), [
            'emergency_contact_name' => 'Mrs Asante', 'emergency_contact_phone' => '+233 24 999 0000', 'phone' => '24 888 7777',
            'address' => 'Kumasi', 'date_of_birth' => '2010-01-01',
        ])->assertRedirect(route('student.profile.edit'));

        $this->kofi->refresh();
        $this->assertSame('0249990000', $this->kofi->emergency_contact_phone);
        $this->assertSame('0248887777', $this->kofi->phone);
        $this->assertSame('2016-03-09', $this->kofi->date_of_birth->format('Y-m-d'));         // unchanged

        $this->post(route('student.profile.update'), ['profile_photo' => UploadedFile::fake()->image('me.jpg')])->assertRedirect();
        $this->kofi->refresh();
        \Storage::disk('public')->assertExists($this->kofi->profile_photo);
        $this->post(route('student.profile.photo.remove'))->assertRedirect();
        $this->assertNull($this->kofi->fresh()->profile_photo);
    }

    public function test_a_missing_date_of_birth_can_be_filled_in_by_the_pupil(): void
    {
        $this->kofi->update(['date_of_birth' => null]);
        $this->actingAs($this->login);

        $this->post(route('student.profile.update'), ['emergency_contact_name' => 'Mrs Asante', 'emergency_contact_phone' => '0244111222', 'date_of_birth' => '2016-03-09'])->assertRedirect();
        $this->assertSame('2016-03-09', $this->kofi->fresh()->date_of_birth->format('Y-m-d'));
    }

    public function test_password_reset_by_sms_and_by_parent_email(): void
    {
        $sms = \Mockery::mock(PastechSmsService::class);
        $sms->shouldReceive('sendSms')->once()->andReturn(['success' => true, 'message' => 'ok']);
        $this->app->instance(PastechSmsService::class, $sms);
        Mail::fake();

        $this->get(route('student.password.request'))->assertOk();

        $this->post(route('student.password.email'), ['phone' => '0200000000'])->assertSessionHasErrors('phone');
        $this->post(route('student.password.email'), ['phone' => '024 411 1222'])->assertSessionHas('status');

        // By email: the pupil has none of their own, so the parent email on file is accepted.
        $this->post(route('student.password.email-request'), ['email' => 'nobody@example.test'])->assertSessionHasErrors('email');
        $this->post(route('student.password.email-request'), ['email' => 'mum@example.test'])->assertSessionHas('status');
        Mail::assertSent(\App\Mail\StudentPasswordResetMail::class, fn ($m) => $m->hasTo('mum@example.test'));

        // The emailed link really resets the password.
        $token = 'known-token-123';
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $this->login->email], ['token' => Hash::make($token), 'created_at' => now()]);
        $this->get(route('student.password.reset', ['token' => $token, 'email' => $this->login->email]))->assertOk();

        $form = ['token' => $token, 'email' => $this->login->email, 'password' => 'resetpass99', 'password_confirmation' => 'resetpass99'];
        $this->post(route('student.password.update'), array_replace($form, ['token' => 'wrong']))->assertSessionHasErrors('token');
        $this->post(route('student.password.update'), $form)->assertRedirect(route('student.login'));
        $this->assertTrue(Hash::check('resetpass99', $this->login->fresh()->password));
        $this->post(route('student.password.update'), $form)->assertSessionHasErrors('token');          // single use

        // An expired link is refused.
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $this->login->email], ['token' => Hash::make($token), 'created_at' => now()->subHours(2)]);
        $this->post(route('student.password.update'), $form)->assertSessionHasErrors('token');
    }

    public function test_a_pupil_sees_only_their_own_fee_account(): void
    {
        $other = $this->pupil('OTHER Pupil', $this->basic4);
        StudentFeeCharge::create(['student_id' => $other->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 9999]);

        $this->actingAs($this->login)->get(route('student.fees.index'))->assertOk()->assertDontSee('9,999.00');
    }
}

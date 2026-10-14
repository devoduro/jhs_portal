<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class StudentsModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Super Admin'));
    }

    public function test_pupil_list_filters_by_search_class_gender_and_status(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $old = $this->pupil('OLD Pupil', $this->basic4, 'Male', ['status' => 'graduated']);

        $this->get(route('students.index'))->assertOk()->assertSee('ASANTE Kofi')->assertDontSee('OLD Pupil');
        $this->get(route('students.index', ['search' => 'Boateng']))->assertSee('BOATENG Akua')->assertDontSee('ASANTE Kofi');
        $this->get(route('students.index', ['class_group_id' => $this->jhs1->id]))->assertSee('ADJEI Esi')->assertDontSee('ASANTE Kofi');
        $this->get(route('students.index', ['gender' => 'Female']))->assertSee('BOATENG Akua')->assertDontSee('ASANTE Kofi');
        $this->get(route('students.index', ['status' => 'graduated']))->assertSee('OLD Pupil')->assertDontSee('ASANTE Kofi');
        $this->get(route('students.index', ['search' => $kofi->emergency_contact_phone]))->assertSee('ASANTE Kofi');
    }

    public function test_pupil_can_be_admitted_viewed_edited_and_validation_is_enforced(): void
    {
        $this->post(route('students.store'), ['gender' => 'Male'])->assertSessionHasErrors('full_name');
        $this->post(route('students.store'), ['full_name' => 'X Y', 'gender' => 'Other'])->assertSessionHasErrors('gender');
        $this->post(route('students.store'), ['full_name' => 'X Y', 'gender' => 'Male'])->assertSessionHasErrors('level');               // no class, no level
        $this->post(route('students.store'), ['full_name' => 'X Y', 'gender' => 'Male', 'level' => 9, 'date_of_birth' => now()->addDay()->toDateString()])->assertSessionHasErrors('date_of_birth');
        $this->post(route('students.store'), ['full_name' => 'X Y', 'gender' => 'Male', 'level' => 9, 'parent_email' => 'not-an-email'])->assertSessionHasErrors('parent_email');

        $this->post(route('students.store'), ['full_name' => 'MENSAH Yaw', 'gender' => 'Male', 'class_group_id' => $this->basic4->id])->assertRedirect();
        $pupil = Student::where('full_name', 'MENSAH Yaw')->first();
        $this->assertSame(9, $pupil->level);                                      // level comes from the class
        $this->assertMatchesRegularExpression('#^SCH/\d{4}/0001$#', $pupil->index_number);
        $this->assertSame('active', $pupil->status);

        $this->post(route('students.store'), ['full_name' => 'OSEI Ama', 'gender' => 'Female', 'class_group_id' => $this->basic4->id]);
        $this->assertStringEndsWith('/0002', Student::where('full_name', 'OSEI Ama')->value('index_number'));

        // Admission numbers are unique.
        $this->post(route('students.store'), ['full_name' => 'DUP', 'gender' => 'Male', 'level' => 9, 'index_number' => $pupil->index_number])->assertSessionHasErrors('index_number');

        $this->get(route('students.show', $pupil))->assertOk()->assertSee('MENSAH Yaw');
        $this->put(route('students.update', $pupil), ['full_name' => 'MENSAH Yaw Kofi', 'gender' => 'Male', 'class_group_id' => $this->jhs1->id, 'status' => 'active'])
            ->assertRedirect(route('students.show', $pupil));
        $pupil->refresh();
        $this->assertSame('MENSAH Yaw Kofi', $pupil->full_name);
        $this->assertSame(12, $pupil->level);                                     // moving class moves level
    }

    public function test_pupil_photo_is_stored_on_the_public_disk(): void
    {
        \Storage::fake('public');
        $this->post(route('students.store'), ['full_name' => 'PHOTO Kid', 'gender' => 'Male', 'level' => 9, 'profile_photo' => UploadedFile::fake()->image('p.jpg')])->assertRedirect();

        \Storage::disk('public')->assertExists(Student::where('full_name', 'PHOTO Kid')->value('profile_photo'));
        $this->post(route('students.store'), ['full_name' => 'BAD Photo', 'gender' => 'Male', 'level' => 9, 'profile_photo' => UploadedFile::fake()->create('doc.pdf', 10)])->assertSessionHasErrors('profile_photo');
    }

    public function test_pupil_with_fee_records_cannot_be_deleted_but_a_clean_record_can(): void
    {
        $paid = $this->pupil('PAID Pupil', $this->basic4);
        StudentPayment::create(['student_id' => $paid->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'amount' => 100, 'payment_method' => 'cash', 'payment_date' => now()]);
        $clean = $this->pupil('CLEAN Pupil', $this->basic4);

        $this->delete(route('students.destroy', $paid))->assertSessionHas('error');
        $this->assertNotNull(Student::find($paid->id));

        $this->delete(route('students.destroy', $clean))->assertRedirect(route('students.index'));
        $this->assertNull(Student::find($clean->id));
    }

    public function test_exports_download_with_the_same_filters_as_the_list(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');

        $excel = $this->get(route('students.export.excel', ['gender' => 'Female']));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-disposition') . $excel->headers->get('content-type'));

        $pdf = $this->get(route('students.export.pdf'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('students.import.template'))->assertOk();
    }

    public function test_portal_logins_are_created_and_the_pupil_can_sign_in(): void
    {
        $withDob = $this->pupil('DOB Pupil', $this->basic4, 'Male', ['date_of_birth' => '2016-03-09']);
        $noDob = $this->pupil('NODOB Pupil', $this->basic4);
        $left = $this->pupil('LEFT Pupil', $this->basic4, 'Male', ['status' => 'withdrawn']);

        $this->get(route('students.create-account', $noDob->id))->assertRedirect()->assertSessionHas('success');
        $account = User::where('student_id', $noDob->id)->first();
        $this->assertSame(User::ROLE_STUDENT, $account->role);
        $this->assertTrue(Hash::check($noDob->index_number, $account->password));     // no DOB: admission number
        $this->assertStringNotContainsString('/', $account->email);
        $this->assertStringNotContainsString('uew.edu.gh', $account->email);

        $this->post(route('students.bulk-create-accounts'))->assertRedirect();
        $this->assertNotNull(User::where('student_id', $withDob->id)->first());
        $this->assertNull(User::where('student_id', $left->id)->first());              // withdrawn pupils are left out
        $this->assertTrue(Hash::check('20160309', User::where('student_id', $withDob->id)->value('password')));

        // Asking twice does not duplicate.
        $this->get(route('students.create-account', $noDob->id))->assertSessionHas('warning');
        $this->assertSame(1, User::where('student_id', $noDob->id)->count());

        $this->post(route('students.bulk-reset-passwords'), ['default_password' => '123'])->assertSessionHasErrors('default_password');
        $this->post(route('students.bulk-reset-passwords'), ['default_password' => 'newpass1'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('newpass1', User::where('student_id', $withDob->id)->value('password')));

        auth()->logout();
        $this->post(route('student.login'), ['index_number' => $withDob->index_number, 'password' => 'newpass1'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_pupils_can_be_imported_from_a_spreadsheet(): void
    {
        $csv = implode("\n", [
            'full_name,gender,class,admission_number,date_of_birth,parent_name,relationship,parent_phone,parent_email,address',
            'MENSAH Kofi,Male,Basic 4A,,2016-05-01,Mrs Mensah,Mother,+233 24 111 2222,mum@example.test,Accra',
            'BOATENG Akua,Female,Nonexistent Class,,,,,,,',
            'ADJEI Esi,Female,JHS 1 Gold,SCH/2026/9001,,,,,,',
        ]);

        $this->post(route('students.import'), ['excel_file' => UploadedFile::fake()->createWithContent('pupils.csv', $csv)])->assertRedirect();

        $kofi = Student::where('full_name', 'MENSAH Kofi')->first();
        $this->assertNotNull($kofi);
        $this->assertSame($this->basic4->id, $kofi->class_group_id);
        $this->assertSame('0241112222', $kofi->emergency_contact_phone);
        $this->assertSame('mum@example.test', $kofi->parent_email);
        $this->assertNull(Student::where('full_name', 'BOATENG Akua')->first());      // unknown class is skipped, not guessed
        $this->assertSame('SCH/2026/9001', Student::where('full_name', 'ADJEI Esi')->value('index_number'));

        // Re-importing the same admission number updates rather than duplicating.
        $again = "full_name,gender,class,admission_number\nADJEI Esi Maud,Female,JHS 1 Gold,SCH/2026/9001";
        $this->post(route('students.import'), ['excel_file' => UploadedFile::fake()->createWithContent('again.csv', $again)]);
        $this->assertSame(1, Student::where('index_number', 'SCH/2026/9001')->count());
        $this->assertSame('ADJEI Esi Maud', Student::where('index_number', 'SCH/2026/9001')->value('full_name'));

        $this->post(route('students.import'), [])->assertSessionHasErrors('excel_file');
    }

    public function test_results_link_redirects_to_the_profile_and_directory_is_read_only(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->get(route('students.results', $kofi))->assertRedirect(route('students.show', $kofi));
        $this->get(route('student-directory.index'))->assertOk()->assertSee('ASANTE Kofi');
        $this->get(route('student-directory.show', $kofi))->assertOk();
    }

    public function test_only_staff_who_manage_pupils_can_reach_the_module(): void
    {
        $this->actingAs($this->teacherUser());
        $this->get(route('students.index'))->assertForbidden();
        $this->get(route('students.create'))->assertForbidden();
        $this->post(route('students.store'), ['full_name' => 'X Y', 'gender' => 'Male', 'level' => 9])->assertForbidden();
    }
}

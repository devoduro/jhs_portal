<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Models\Venue;
use App\Services\PastechSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class StaffAndTimetableModuleTest extends TestCase
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

    // -------------------------------------------------------------- departments

    public function test_departments_have_unique_names_and_cannot_be_deleted_while_they_have_teachers(): void
    {
        $this->get(route('departments.index'))->assertOk();
        $this->get(route('departments.create'))->assertOk();

        $this->post(route('departments.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('departments.store'), ['name' => 'Languages'])->assertRedirect(route('departments.index'));
        $this->post(route('departments.store'), ['name' => 'Languages'])->assertSessionHasErrors('name');

        $dept = Department::where('name', 'Languages')->first();
        $this->put(route('departments.update', $dept), ['name' => 'Languages'])->assertSessionHasNoErrors();   // own name is fine
        $this->put(route('departments.update', $dept), ['name' => 'Languages & Arts'])->assertRedirect();
        $this->get(route('departments.edit', $dept))->assertOk()->assertSee('Languages &amp; Arts', false);

        $this->teacher->update(['department_id' => $dept->id]);
        $this->delete(route('departments.destroy', $dept))->assertSessionHas('error');
        $this->teacher->update(['department_id' => null]);
        $this->delete(route('departments.destroy', $dept))->assertSessionHas('success');
        $this->assertNull(Department::find($dept->id));
    }

    // ------------------------------------------------------------------ venues

    public function test_venues_validate_and_cannot_be_deleted_while_used_in_the_timetable(): void
    {
        $this->get(route('venues.index'))->assertOk();
        $this->get(route('venues.create'))->assertOk();

        $this->post(route('venues.store'), ['name' => 'Hall', 'max_concurrent_classes' => 0])->assertSessionHasErrors('max_concurrent_classes');
        $this->post(route('venues.store'), ['name' => 'Hall', 'capacity' => 0, 'max_concurrent_classes' => 1])->assertSessionHasErrors('capacity');
        $this->post(route('venues.store'), ['name' => 'Hall', 'capacity' => 120, 'location' => 'Block A', 'max_concurrent_classes' => 2])->assertRedirect(route('venues.index'));
        $this->post(route('venues.store'), ['name' => 'Hall', 'max_concurrent_classes' => 1])->assertSessionHasErrors('name');

        $venue = Venue::where('name', 'Hall')->first();
        $this->put(route('venues.update', $venue), ['name' => 'Main Hall', 'max_concurrent_classes' => 2])->assertRedirect();
        $this->assertSame('Main Hall', $venue->fresh()->name);
        $this->get(route('venues.edit', $venue))->assertOk();

        $this->lesson(['venue_id' => $venue->id]);
        $this->delete(route('venues.destroy', $venue))->assertSessionHas('error');
        TimetableEntry::query()->delete();
        $this->delete(route('venues.destroy', $venue))->assertSessionHas('success');
    }

    // --------------------------------------------------------------- timetable

    private function lesson(array $override = []): TimetableEntry
    {
        return TimetableEntry::create($override + [
            'class_group_id' => $this->basic4->id,
            'subject_id' => Subject::where('level', 9)->first()->id,
            'teacher_id' => $this->teacher->id,
            'semester_id' => $this->term->id,
            'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '09:00',
        ]);
    }

    private function lessonForm(array $override = []): array
    {
        return $override + [
            'class_group_id' => $this->basic4->id,
            'subject_id' => Subject::where('level', 9)->first()->id,
            'teacher_id' => $this->teacher->id,
            'venue_id' => Venue::firstOrCreate(['name' => 'Room 1'], ['capacity' => 40, 'max_concurrent_classes' => 1])->id,
            'semester_id' => $this->term->id,
            'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '11:00',
        ];
    }

    public function test_a_lesson_can_be_scheduled_edited_and_deleted_for_a_basic_school_class(): void
    {
        $this->get(route('timetable.create'))->assertOk();
        $this->post(route('timetable.store'), $this->lessonForm())->assertSessionHasNoErrors()->assertRedirect(route('timetable.index'));

        $entry = TimetableEntry::first();
        $this->assertNotNull($entry);
        $this->get(route('timetable.edit', $entry))->assertOk();

        $this->put(route('timetable.update', $entry), $this->lessonForm(['start_time' => '10:30', 'end_time' => '11:30']))->assertSessionHasNoErrors();
        $this->assertSame('10:30', substr($entry->fresh()->start_time, 0, 5));

        $this->delete(route('timetable.destroy', $entry))->assertRedirect(route('timetable.index'));
        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_a_subject_from_another_level_cannot_go_on_a_class_timetable(): void
    {
        $jhsSubject = Subject::where('level', 12)->first();

        $this->post(route('timetable.store'), $this->lessonForm(['subject_id' => $jhsSubject->id]))->assertSessionHasErrors('subject_id');
        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_clashes_for_class_teacher_and_venue_are_refused(): void
    {
        $room = Venue::create(['name' => 'Room 1', 'capacity' => 40, 'max_concurrent_classes' => 1]);
        $this->lesson(['venue_id' => $room->id, 'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '11:00']);

        // Same class, overlapping time.
        $this->post(route('timetable.store'), $this->lessonForm(['venue_id' => null, 'is_virtual' => 1, 'teacher_id' => null, 'start_time' => '10:30', 'end_time' => '11:30']))->assertSessionHasErrors();
        // Same teacher in another class at the same time.
        $this->post(route('timetable.store'), $this->lessonForm(['class_group_id' => $this->jhs1->id, 'subject_id' => Subject::where('level', 12)->first()->id, 'venue_id' => null, 'is_virtual' => 1]))->assertSessionHasErrors();
        // Same room, different class and teacher.
        $this->post(route('timetable.store'), $this->lessonForm(['class_group_id' => $this->jhs1->id, 'subject_id' => Subject::where('level', 12)->first()->id, 'teacher_id' => null, 'venue_id' => $room->id]))->assertSessionHasErrors('start_time');

        // Back-to-back lessons are fine.
        $this->post(route('timetable.store'), $this->lessonForm(['venue_id' => $room->id, 'start_time' => '11:00', 'end_time' => '12:00']))->assertSessionHasNoErrors();
        $this->assertSame(2, TimetableEntry::count());

        // Times must be in order.
        $this->post(route('timetable.store'), $this->lessonForm(['start_time' => '14:00', 'end_time' => '13:00']))->assertSessionHasErrors('end_time');
    }

    public function test_timetable_screens_and_filters(): void
    {
        $this->lesson();
        $filters = ['semester_id' => $this->term->id, 'class_group_id' => $this->basic4->id];

        $this->get(route('timetable.index'))->assertOk();
        $this->get(route('timetable.index', $filters))->assertOk();
        $this->get(route('timetable.index', ['semester_id' => $this->term->id, 'teacher_id' => $this->teacher->id]))->assertOk();
        $this->get(route('timetable.print', $filters))->assertOk();
    }

    // ---------------------------------------------------------------- teachers

    public function test_teacher_screens_filters_exports_and_import(): void
    {
        $this->get(route('teachers.index'))->assertOk()->assertSee('Ama Owusu');
        $this->get(route('teachers.index', ['search' => 'Owusu']))->assertSee('Ama Owusu');
        $this->get(route('teachers.index', ['search' => 'nobody-here']))->assertDontSee('Ama Owusu');
        $this->get(route('teachers.show', $this->teacher))->assertOk()->assertSee('Ama Owusu');
        $this->get(route('teachers.edit', $this->teacher))->assertOk();
        $this->get(route('teachers.export.excel'))->assertOk();
        $this->assertStringStartsWith('%PDF', $this->get(route('teachers.export.pdf'))->getContent());
        $this->get(route('teachers.import.template'))->assertOk();

        $csv = "name,email,phone,staff_id,department\nKojo Badu,kojo@example.test,+233 24 555 1212,TCH090,\n,,,,";
        $this->post(route('teachers.import.store'), ['excel_file' => UploadedFile::fake()->createWithContent('t.csv', $csv)])->assertRedirect();
        $kojo = Teacher::where('email', 'kojo@example.test')->first();
        $this->assertNotNull($kojo);
        $this->assertSame('0245551212', $kojo->phone);
    }

    public function test_teacher_update_validates_and_keeps_unique_fields(): void
    {
        $other = Teacher::create(['name' => 'Kofi Boadi', 'email' => 'kofi@example.test', 'staff_id' => 'TCH077']);

        $this->put(route('teachers.update', $this->teacher), ['name' => 'Ama Owusu', 'email' => 'kofi@example.test'])->assertSessionHasErrors('email');
        $this->put(route('teachers.update', $this->teacher), ['name' => 'Ama Owusu', 'staff_id' => 'TCH077'])->assertSessionHasErrors('staff_id');
        $this->put(route('teachers.update', $this->teacher), ['name' => 'Ama Owusu-Mensah', 'email' => 'ama@example.test', 'staff_id' => 'T001', 'phone' => '024 111 2222'])->assertSessionHasNoErrors();

        $this->assertSame('Ama Owusu-Mensah', $this->teacher->fresh()->name);
        $this->assertSame('0241112222', $this->teacher->fresh()->phone);
    }

    public function test_teacher_login_lifecycle_and_delete_guards(): void
    {
        $free = Teacher::create(['name' => 'Free Teacher', 'email' => 'free@example.test', 'phone' => '0244000000', 'staff_id' => 'TCH050']);
        $noEmail = Teacher::create(['name' => 'No Email', 'staff_id' => 'TCH051']);

        $this->post(route('teachers.create-account', $noEmail))->assertSessionHas('error');
        $this->post(route('teachers.create-account', $free))->assertSessionHas('success');
        $login = User::where('teacher_id', $free->id)->first();
        $this->assertTrue($login->hasRole('Teacher'));
        $this->assertEquals(1, $login->first_login);

        $this->post(route('teachers.create-account', $free))->assertSessionHas('warning');          // not twice
        $oldHash = $login->password;
        $this->post(route('teachers.reset-password', $free))->assertSessionHas('success');
        $this->assertNotSame($oldHash, $login->fresh()->password);
        $this->post(route('teachers.reset-password', $noEmail))->assertSessionHas('error');

        // A class teacher or a subject teacher cannot be deleted.
        $this->delete(route('teachers.destroy', $this->teacher))->assertSessionHas('error');
        $this->assertNotNull(Teacher::find($this->teacher->id));

        // A free teacher can - and their login goes with them.
        $this->delete(route('teachers.destroy', $free))->assertSessionHas('success');
        $this->assertNull(Teacher::find($free->id));
        $this->assertNull(User::find($login->id));
    }

    public function test_teacher_self_service_profile_and_timetable(): void
    {
        $this->lesson();
        $this->actingAs($this->teacherUser());

        $this->get(route('teacher.profile.edit'))->assertOk();
        $this->put(route('teacher.profile.update'), ['email' => 'ama.new@example.test', 'phone' => '+233 20 999 8888'])->assertRedirect();
        $this->assertSame('0209998888', $this->teacher->fresh()->phone);
        $this->put(route('teacher.profile.update'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        $this->get(route('teacher.timetable'))->assertOk();
        $this->get(route('teacher.timetable.print', ['semester_id' => $this->term->id]))->assertOk();

        // An account that is not linked to a teacher profile has nothing here.
        $this->actingAs($this->staff('Accountant'));
        $this->get(route('teacher.profile.edit'))->assertForbidden();
        $this->get(route('teacher.timetable'))->assertForbidden();
    }
}

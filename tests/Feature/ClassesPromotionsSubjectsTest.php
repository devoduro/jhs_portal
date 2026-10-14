<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\Result;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ClassesPromotionsSubjectsTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Super Admin'));
    }

    // ---------------------------------------------------------------- classes

    public function test_classes_can_be_created_edited_and_deleted_with_guards(): void
    {
        $this->get(route('class-groups.index'))->assertOk()->assertSee('Basic 4A');
        $this->get(route('class-groups.create'))->assertOk();

        $this->post(route('class-groups.store'), ['name' => 'Basic 5A', 'level' => 99])->assertSessionHasErrors('level');
        $this->post(route('class-groups.store'), ['name' => '', 'level' => 10])->assertSessionHasErrors('name');
        $this->post(route('class-groups.store'), ['name' => 'Basic 4A', 'level' => 9])->assertSessionHasErrors('name');       // names are unique

        $this->post(route('class-groups.store'), ['name' => 'Basic 5A', 'level' => 10, 'class_teacher_id' => $this->teacher->id])->assertRedirect(route('class-groups.index'));
        $class = ClassGroup::where('name', 'Basic 5A')->first();
        $this->assertSame(10, $class->level);

        $pupil = $this->pupil('ASANTE Kofi', $class);
        $this->put(route('class-groups.update', $class), ['name' => 'Basic 5 Gold', 'level' => 11])->assertRedirect();
        $this->assertSame(11, $pupil->fresh()->level);                          // pupils follow their class's level

        // Renaming a class to its own name is not a clash.
        $this->put(route('class-groups.update', $class), ['name' => 'Basic 5 Gold', 'level' => 11])->assertSessionHasNoErrors();

        $this->delete(route('class-groups.destroy', $class))->assertSessionHas('error');         // has a pupil
        $this->assertNotNull(ClassGroup::find($class->id));
        $pupil->delete();
        $this->delete(route('class-groups.destroy', $class))->assertSessionHas('success');
        $this->assertNull(ClassGroup::find($class->id));
    }

    public function test_pupils_are_placed_into_a_class_and_only_active_pupils_move(): void
    {
        $a = $this->pupil('A One', $this->basic4);
        $b = $this->pupil('B Two', $this->basic4);
        $gone = $this->pupil('C Left', $this->basic4, 'Male', ['status' => 'withdrawn']);

        $this->get(route('class-groups.assign-students', $this->basic4))->assertOk()->assertSee('A One')->assertDontSee('C Left');

        $this->post(route('class-groups.assign-students.store', $this->basic4), ['student_ids' => [$a->id]])->assertRedirect();
        $this->assertSame($this->basic4->id, $a->fresh()->class_group_id);
        $this->assertNull($b->fresh()->class_group_id);                          // un-ticked pupils are released

        // A withdrawn pupil cannot be slipped into a class by posting their id.
        $this->post(route('class-groups.assign-students.store', $this->jhs1), ['student_ids' => [$gone->id]]);
        $this->assertNotSame($this->jhs1->id, $gone->fresh()->class_group_id);
    }

    public function test_class_list_import_places_pupils_by_admission_number(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $csv = "index_number,class_name\n{$kofi->index_number},JHS 1 Gold\nNOPE/1,JHS 1 Gold";

        $this->post(route('class-groups.import.store'), ['excel_file' => UploadedFile::fake()->createWithContent('c.csv', $csv)])->assertRedirect();
        $this->assertSame($this->jhs1->id, $kofi->fresh()->class_group_id);
        $this->get(route('class-groups.import.template'))->assertOk();
        $this->get(route('class-groups.import.form'))->assertOk();
    }

    // ------------------------------------------------------------- promotions

    public function test_promotion_moves_ticked_pupils_up_and_leaves_repeaters(): void
    {
        $five = ClassGroup::create(['name' => 'Basic 5A', 'level' => 10]);
        $up = $this->pupil('UP Pupil', $this->basic4);
        $repeat = $this->pupil('REPEAT Pupil', $this->basic4);

        $this->get(route('promotions.index'))->assertOk()->assertSee('Basic 4A');
        $this->post(route('promotions.preview'), ['class_group_id' => $this->basic4->id])->assertOk()->assertSee('UP Pupil');

        $this->post(route('promotions.store'), ['class_group_id' => $this->basic4->id, 'target_class_group_id' => $five->id, 'student_ids' => [$up->id]])->assertRedirect();
        $up->refresh();
        $this->assertSame(10, $up->level);
        $this->assertSame($five->id, $up->class_group_id);
        $this->assertSame($this->basic4->id, $repeat->fresh()->class_group_id);          // not ticked: repeats

        // Moving to a class of the wrong level is refused.
        $this->post(route('promotions.store'), ['class_group_id' => $this->basic4->id, 'target_class_group_id' => $this->jhs1->id, 'student_ids' => [$repeat->id]])->assertSessionHas('error');
        $this->assertSame(9, $repeat->fresh()->level);

        // Nobody ticked: nothing moves.
        $this->post(route('promotions.store'), ['class_group_id' => $this->basic4->id, 'student_ids' => []])->assertSessionHas('error');
    }

    public function test_final_class_pupils_complete_school_and_keep_their_results(): void
    {
        $final = ClassGroup::create(['name' => 'JHS 3 Gold', 'level' => 14]);
        $pupil = $this->pupil('FINAL Pupil', $final);
        $subject = Subject::where('level', 14)->first();
        Result::create(['student_id' => $pupil->id, 'subject_id' => $subject->id, 'semester_id' => $this->term->id, 'academic_year_id' => $this->term->academic_year_id, 'class_group_id' => $final->id, 'score' => 80, 'grade' => '1', 'grade_point' => 1]);

        $this->post(route('promotions.store'), ['class_group_id' => $final->id, 'student_ids' => [$pupil->id], 'graduation_year' => 2027])->assertRedirect();

        $pupil->refresh();
        $this->assertSame('graduated', $pupil->status);
        $this->assertSame(2027, (int) $pupil->graduation_year);
        $this->assertNull($pupil->class_group_id);
        $this->assertSame(1, Result::where('student_id', $pupil->id)->count());
    }

    // --------------------------------------------------------------- subjects

    public function test_a_subject_is_added_for_several_levels_and_not_twice(): void
    {
        $this->get(route('subjects.index'))->assertOk();
        $this->get(route('subjects.create'))->assertOk();

        $this->post(route('subjects.store'), ['code_prefix' => 'AB1', 'title' => 'Bad', 'levels' => [9]])->assertSessionHasErrors('code_prefix');
        $this->post(route('subjects.store'), ['code_prefix' => 'ZZZ', 'title' => 'Zulu', 'levels' => []])->assertSessionHasErrors('levels');

        // The form posts the checkbox as 1 (0 when unticked).
        $this->post(route('subjects.store'), ['code_prefix' => 'ZZZ', 'title' => 'Zulu', 'levels' => [9, 12], 'is_core' => 1, 'teacher_ids' => [$this->teacher->id]])
            ->assertSessionHasNoErrors()->assertRedirect(route('subjects.index'));

        $nine = Subject::where('code', 'ZZZ-B4')->first();
        $this->assertNotNull($nine);
        $this->assertTrue((bool) $nine->is_core);
        $this->assertNotNull(Subject::where('code', 'ZZZ-J1')->first());
        $this->assertTrue($nine->teachers->contains($this->teacher));

        $this->post(route('subjects.store'), ['code_prefix' => 'ZZZ', 'title' => 'Zulu', 'levels' => [9]])->assertSessionHas('error');
        $this->assertSame(1, Subject::where('code', 'ZZZ-B4')->count());
    }

    public function test_subject_edit_delete_and_screens(): void
    {
        $subject = Subject::create(['code' => 'QQ-B4', 'title' => 'Quill', 'level' => 9, 'credit_hours' => 1, 'is_core' => false]);

        $this->get(route('subjects.edit', $subject))->assertOk();
        $this->put(route('subjects.update', $subject), ['code' => 'qq-b4x', 'title' => 'Quill Work', 'level' => 9, 'is_core' => 1])->assertRedirect();
        $subject->refresh();
        $this->assertSame('QQ-B4X', $subject->code);
        $this->assertTrue((bool) $subject->is_core);

        $this->put(route('subjects.update', $subject), ['code' => Subject::where('level', 9)->where('id', '!=', $subject->id)->value('code'), 'title' => 'x', 'level' => 9])->assertSessionHasErrors('code');

        $this->get(route('subjects.show', $subject))->assertRedirect();
        $this->get(route('subjects.students', $subject))->assertOk();
        $this->get(route('subjects.students.export', $subject))->assertOk();
        $this->get(route('subjects.students.attendance', $subject))->assertOk();
        $this->get(route('subjects.results', $subject))->assertOk();

        // A subject with scores cannot be deleted.
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        Result::create(['student_id' => $kofi->id, 'subject_id' => $subject->id, 'semester_id' => $this->term->id, 'academic_year_id' => $this->term->academic_year_id, 'class_group_id' => $this->basic4->id, 'score' => 60, 'grade' => 'P', 'grade_point' => 3]);
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('error');
        $this->assertNotNull(Subject::find($subject->id));

        Result::query()->delete();
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('success');
        $this->assertNull(Subject::find($subject->id));
    }

    public function test_classes_promotions_and_subjects_are_closed_to_ordinary_teachers(): void
    {
        $this->actingAs($this->teacherUser());

        foreach ([route('class-groups.index'), route('promotions.index'), route('subjects.index'), route('subjects.create')] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('promotions.store'), ['class_group_id' => $this->basic4->id])->assertForbidden();
        $this->post(route('class-groups.store'), ['name' => 'Hack', 'level' => 9])->assertForbidden();
    }
}

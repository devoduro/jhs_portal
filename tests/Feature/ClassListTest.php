<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ClassListTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('BOATENG Akua', $this->basic4, 'Female');
    }

    public function test_admin_sees_every_class_and_can_open_pdf_and_excel(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get(route('class-lists.index'))->assertOk()->assertSee('Basic 4A')->assertSee('JHS 1 Gold');
        $this->get(route('class-lists.show', $this->basic4))->assertOk()->assertSee('2 pupils');

        $pdf = $this->get(route('class-lists.file', [$this->basic4, 'cols' => ['index_number', 'phone', 'signature'], 'blank' => 5, 'download' => 1]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('class-lists.excel', [$this->basic4, 'gender' => 'Female']))->assertOk();
    }

    public function test_teacher_prints_own_class_but_not_others(): void
    {
        $this->actingAs($this->teacherUser());

        $this->get(route('class-lists.index'))->assertOk()->assertSee('Basic 4A')->assertDontSee('JHS 1 Gold');
        $this->get(route('class-lists.show', $this->basic4))->assertOk();
        $this->get(route('class-lists.file', $this->basic4))->assertOk();
        $this->get(route('class-lists.show', $this->jhs1))->assertForbidden();
        $this->get(route('class-lists.file', $this->jhs1))->assertForbidden();
        $this->get(route('class-lists.excel', $this->jhs1))->assertForbidden();
    }

    public function test_list_options_filter_and_old_print_url_redirects(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get(route('class-lists.show', [$this->basic4, 'gender' => 'Female', 'submitted' => 1, 'cols' => ['gender']]))
            ->assertOk()->assertViewHas('students', fn ($s) => $s->count() === 1 && $s->first()->full_name === 'BOATENG Akua')
            ->assertViewHas('columns', ['gender']);
        $this->get(route('class-lists.show', [$this->basic4, 'submitted' => 1]))->assertViewHas('columns', []);

        $this->get(route('class-groups.print', $this->basic4))->assertRedirect(route('class-lists.show', $this->basic4));
    }

    public function test_teacher_can_open_term_reports_for_a_class_they_teach_a_subject_in(): void
    {
        $jhs1Subject = \App\Models\Subject::where('level', 12)->first();
        $this->actingAs($this->teacherUser());

        // Not their class and not their level: nothing to edit.
        $this->get(route('term-reports.index', ['class_group_id' => $this->jhs1->id]))->assertOk()->assertSee('not assigned to that class');
        $this->post(route('term-reports.save', $this->jhs1), ['semester_id' => $this->term->id])->assertForbidden();

        // Once assigned a subject at JHS 1's level, the class opens and can be saved.
        $this->teacher->subjects()->attach($jhs1Subject->id);
        $this->get(route('term-reports.index', ['class_group_id' => $this->jhs1->id, 'semester_id' => $this->term->id]))->assertOk()->assertDontSee('not assigned to that class');
        $this->post(route('term-reports.save', $this->jhs1), ['semester_id' => $this->term->id])->assertRedirect();
    }
}

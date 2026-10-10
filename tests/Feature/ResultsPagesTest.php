<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ResultsPagesTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private Result $b4Result;
    private Result $j1Result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();

        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->actingAs($this->staff('Exams Officer'));

        $b4 = Subject::where('level', 9)->first();
        $this->post(route('scores.save', [$this->basic4, $b4]), ['semester_id' => $this->term->id, 'scores' => [
            $kofi->id => ['class_score' => 40, 'exam_score' => 45], $akua->id => ['class_score' => 30, 'exam_score' => 30],
        ]]);
        $j1 = Subject::where('level', 12)->first();
        $this->post(route('scores.save', [$this->jhs1, $j1]), ['semester_id' => $this->term->id, 'scores' => [$esi->id => ['class_score' => 35, 'exam_score' => 40]]]);

        $this->b4Result = Result::where('student_id', $kofi->id)->first();
        $this->j1Result = Result::where('student_id', $esi->id)->first();
    }

    public function test_result_detail_shows_split_grade_and_position(): void
    {
        $this->get(route('results.show', $this->b4Result))
            ->assertOk()
            ->assertSee('ASANTE Kofi')
            ->assertSee('Basic 4A')
            ->assertSee('85')
            ->assertSee('Advanced')
            ->assertSee('1st out of 2');
    }

    public function test_result_list_filters_by_class_and_term(): void
    {
        $this->get(route('results.index', ['class_group_id' => $this->basic4->id, 'semester_id' => $this->term->id]))
            ->assertOk()->assertSee('ASANTE Kofi')->assertDontSee('ADJEI Esi');
    }

    public function test_old_add_edit_and_upload_links_open_the_score_sheet(): void
    {
        $sheet = route('scores.sheet', ['classGroup' => $this->basic4, 'subject' => $this->b4Result->subject_id, 'semester_id' => $this->term->id]);

        $this->get(route('results.edit', $this->b4Result))->assertRedirect($sheet);
        $this->get(route('results.create', ['student_id' => $this->b4Result->student_id, 'subject_id' => $this->b4Result->subject_id, 'semester_id' => $this->term->id]))->assertRedirect($sheet);
        $this->get(route('results.bulk-create'))->assertRedirect(route('scores.index'));
        $this->get('/bulkresults/upload')->assertRedirect('/scores');
    }

    public function test_teacher_sees_only_own_class_results_and_cannot_delete(): void
    {
        $teacher = $this->teacherUser();

        $this->actingAs($teacher)->get(route('results.show', $this->b4Result))->assertOk();
        $this->get(route('results.edit', $this->b4Result))->assertRedirect();
        $this->get(route('results.show', $this->j1Result))->assertForbidden();
        $this->get(route('results.index'))->assertOk()->assertSee('ASANTE Kofi')->assertDontSee('ADJEI Esi');
        $this->delete(route('results.destroy', $this->b4Result))->assertForbidden();
    }

    public function test_exams_officer_can_delete_a_score(): void
    {
        $this->delete(route('results.destroy', $this->b4Result))->assertRedirect(route('results.index'));
        $this->assertModelMissing($this->b4Result);
    }
}

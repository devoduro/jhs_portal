<?php

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\Result;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class ScoreSheetDesignerTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function sheet(array $rows, array $extra = []): array
    {
        return ['components' => $rows] + $extra;
    }

    private function standardSheet(): array
    {
        return $this->sheet([
            ['label' => 'Exercises', 'type' => 'class', 'max_mark' => 10],
            ['label' => 'Class Test', 'type' => 'class', 'max_mark' => 20],
            ['label' => 'Exam', 'type' => 'exam', 'max_mark' => 70],
        ]);
    }

    public function test_admin_can_design_a_levels_score_sheet(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get(route('assessment-settings.index'))->assertOk()->assertSee('Basic 4');
        $this->put(route('assessment-settings.update', 9), $this->standardSheet())->assertRedirect(route('assessment-settings.index'));

        $this->assertSame(['Exercises', 'Class Test', 'Exam'], AssessmentComponent::forLevel(9)->pluck('label')->all());
        $this->get(route('assessment-settings.edit', 9))->assertOk()->assertSee('Class Test');
    }

    public function test_a_sheet_must_add_up_to_100_and_have_class_and_exam_columns(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->put(route('assessment-settings.update', 9), $this->sheet([
            ['label' => 'Test', 'type' => 'class', 'max_mark' => 30], ['label' => 'Exam', 'type' => 'exam', 'max_mark' => 60],
        ]))->assertSessionHasErrors('components');

        $this->put(route('assessment-settings.update', 9), $this->sheet([
            ['label' => 'Test', 'type' => 'class', 'max_mark' => 40], ['label' => 'Project', 'type' => 'class', 'max_mark' => 60],
        ]))->assertSessionHasErrors('components');

        $this->put(route('assessment-settings.update', 9), $this->sheet([
            ['label' => 'Test', 'type' => 'class', 'max_mark' => 50], ['label' => 'test', 'type' => 'exam', 'max_mark' => 50],
        ]))->assertSessionHasErrors('components');

        $this->assertSame(0, AssessmentComponent::count());
    }

    public function test_teachers_cannot_change_score_sheets(): void
    {
        $this->actingAs($this->teacherUser())->put(route('assessment-settings.update', 9), $this->standardSheet())->assertForbidden();
    }

    public function test_marks_entered_on_a_designed_sheet_add_up_into_class_and_exam_scores(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();
        $this->actingAs($this->staff('Super Admin'));
        $this->put(route('assessment-settings.update', 9), $this->standardSheet());
        [$exercises, $test, $exam] = AssessmentComponent::forLevel(9)->all();

        $this->get(route('scores.sheet', [$this->basic4, $subject, 'semester_id' => $this->term->id]))
            ->assertOk()->assertSee('Exercises')->assertSee('Class Test');

        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [
            $kofi->id => ["c{$exercises->id}" => 8, "c{$test->id}" => 15, "c{$exam->id}" => 60],
        ]])->assertRedirect();

        $result = Result::where('student_id', $kofi->id)->first();
        $this->assertEquals(23, $result->class_score);
        $this->assertEquals(60, $result->exam_score);
        $this->assertEquals(83, $result->score);
        $this->assertSame(3, $result->componentScores()->count());

        // A mark above a column's maximum is refused.
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [
            $kofi->id => ["c{$exercises->id}" => 11],
        ]])->assertSessionHasErrors();
        $this->assertEquals(83, $result->fresh()->score);
    }

    public function test_columns_with_marks_cannot_be_removed_and_levels_can_share_a_sheet(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();
        $this->actingAs($this->staff('Super Admin'));
        $this->put(route('assessment-settings.update', 9), $this->standardSheet() + ['apply_to' => [10]]);
        $this->assertCount(3, AssessmentComponent::forLevel(10));

        [$exercises, $test, $exam] = AssessmentComponent::forLevel(9)->all();
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [$kofi->id => ["c{$test->id}" => 15]]]);

        // Dropping the marked "Class Test" column is refused...
        $this->put(route('assessment-settings.update', 9), $this->sheet([
            ['id' => $exercises->id, 'label' => 'Exercises', 'type' => 'class', 'max_mark' => 30],
            ['id' => $exam->id, 'label' => 'Exam', 'type' => 'exam', 'max_mark' => 70],
        ]))->assertSessionHasErrors('components');
        $this->assertCount(3, AssessmentComponent::forLevel(9));

        // ...but renaming it and re-weighting is fine, and the marks stay attached.
        $this->put(route('assessment-settings.update', 9), $this->sheet([
            ['id' => $exercises->id, 'label' => 'Homework', 'type' => 'class', 'max_mark' => 10],
            ['id' => $test->id, 'label' => 'Quiz', 'type' => 'class', 'max_mark' => 20],
            ['id' => $exam->id, 'label' => 'Exam', 'type' => 'exam', 'max_mark' => 70],
        ]))->assertSessionDoesntHaveErrors();
        $this->assertSame('Quiz', $test->fresh()->label);
        $this->assertSame(1, \App\Models\ResultComponentScore::where('assessment_component_id', $test->id)->count());

        $this->delete(route('assessment-settings.destroy', 9))->assertSessionHas('error');
        $this->delete(route('assessment-settings.destroy', 10))->assertSessionHas('success');
        $this->assertCount(0, AssessmentComponent::forLevel(10));
    }

    public function test_results_scored_before_a_sheet_was_designed_keep_their_totals(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [$kofi->id => ['class_score' => 40, 'exam_score' => 40]]]);

        $this->put(route('assessment-settings.update', 9), $this->standardSheet());

        // Saving the new sheet with that pupil's boxes left empty must not wipe the old result.
        $this->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [$kofi->id => []]]);
        $this->assertEquals(80, Result::where('student_id', $kofi->id)->value('score'));

        $this->get(route('scores.sheet', [$this->basic4, $subject, 'semester_id' => $this->term->id]))->assertSee('Earlier total kept');
    }

    public function test_old_ca_score_settings_url_redirects_to_the_new_screen(): void
    {
        $this->actingAs($this->staff('Super Admin'))->get('/ca-score-settings')->assertRedirect('/assessment-settings');
    }
}

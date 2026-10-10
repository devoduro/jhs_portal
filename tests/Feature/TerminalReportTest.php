<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\Subject;
use App\Models\TermReport;
use App\Services\TerminalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class TerminalReportTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function enterScores($class, array $scoresByName): void
    {
        $pupils = collect($scoresByName)->keys()->mapWithKeys(fn ($name) => [$name => $this->pupil($name, $class)]);
        foreach (Subject::where('level', $class->level)->get() as $subject) {
            $this->post(route('scores.save', [$class, $subject]), [
                'semester_id' => $this->term->id,
                'scores' => $pupils->mapWithKeys(fn ($p, $name) => [$p->id => ['class_score' => $scoresByName[$name][0], 'exam_score' => $scoresByName[$name][1]]])->all(),
            ])->assertRedirect();
        }
    }

    public function test_scores_are_graded_and_ranked_with_ties(): void
    {
        $this->actingAs($this->staff('Exams Officer'));
        $this->enterScores($this->basic4, [
            'ASANTE Kofi' => [45, 48], 'OWUSU Abena' => [45, 48], 'BOATENG Akua' => [40, 30], 'DARKO Kwabena' => [20, 15],
        ]);

        $result = Result::whereHas('student', fn ($q) => $q->where('full_name', 'ASANTE Kofi'))->first();
        $this->assertEquals(93, $result->score);
        $this->assertSame('A', $result->grade);
        $this->assertSame('Advanced', $result->remark);

        $data = TerminalReportService::forClass($this->basic4, $this->term);
        $positions = $data['pupils']->mapWithKeys(fn ($p) => [$p['student']->full_name => $p['position']]);
        $this->assertSame(['ASANTE Kofi' => 1, 'BOATENG Akua' => 3, 'DARKO Kwabena' => 4, 'OWUSU Abena' => 1], $positions->sortKeys()->all());
        $this->assertSame(4, $data['roll']);
    }

    public function test_jhs_uses_bece_grades_and_aggregate(): void
    {
        $this->actingAs($this->staff('Exams Officer'));
        $this->enterScores($this->jhs1, ['ADJEI Esi' => [42, 43], 'QUAYE Nii' => [25, 25]]);

        $data = TerminalReportService::forClass($this->jhs1, $this->term);
        $esi = $data['pupils']->first(fn ($p) => $p['student']->full_name === 'ADJEI Esi');
        $this->assertSame('1', $esi['rows']->first()['grade']);
        $this->assertSame(6, $esi['aggregate']);
    }

    public function test_scores_above_the_maximum_are_rejected(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();

        $this->actingAs($this->staff('Exams Officer'))
            ->post(route('scores.save', [$this->basic4, $subject]), ['semester_id' => $this->term->id, 'scores' => [$kofi->id => ['class_score' => 70, 'exam_score' => 10]]])
            ->assertSessionHasErrors();

        $this->assertSame(0, Result::count());
    }

    public function test_class_teacher_can_score_own_class_only(): void
    {
        $user = $this->teacherUser();
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1);
        $b4Subject = Subject::where('level', 9)->first();
        $j1Subject = Subject::where('level', 12)->first();

        $this->actingAs($user)->get(route('scores.sheet', [$this->basic4, $b4Subject, 'semester_id' => $this->term->id]))->assertOk()->assertSee('ASANTE Kofi');
        $this->get(route('scores.sheet', [$this->jhs1, $j1Subject, 'semester_id' => $this->term->id]))->assertForbidden();
        $this->post(route('scores.save', [$this->jhs1, $j1Subject]), ['semester_id' => $this->term->id, 'scores' => []])->assertForbidden();
        $this->get(route('scores.broadsheet', $this->jhs1))->assertForbidden();
    }

    public function test_class_teacher_saves_remarks_but_not_head_teacher_remark(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);

        $this->actingAs($this->teacherUser())->post(route('term-reports.save', $this->basic4), [
            'semester_id' => $this->term->id,
            'attendance_days' => 62,
            'reports' => [$kofi->id => ['days_present' => 60, 'conduct' => 'Very Good', 'class_teacher_remark' => 'Keep it up.', 'head_teacher_remark' => 'Sneaky']],
        ])->assertRedirect();

        $report = TermReport::first();
        $this->assertSame(60, $report->days_present);
        $this->assertSame('Keep it up.', $report->class_teacher_remark);
        $this->assertNull($report->head_teacher_remark);
        $this->assertSame(62, $this->term->fresh()->attendance_days);

        $this->post(route('term-reports.save', $this->jhs1), ['semester_id' => $this->term->id])->assertForbidden();
    }

    public function test_report_cards_render_for_class_single_pupil_and_parent_link(): void
    {
        $this->actingAs($this->staff('Exams Officer'));
        $this->enterScores($this->basic4, ['ASANTE Kofi' => [45, 48], 'BOATENG Akua' => [30, 30]]);
        $kofi = \App\Models\Student::where('full_name', 'ASANTE Kofi')->first();

        $this->get(route('report-cards.bulk-print', ['class_group_id' => $this->basic4->id, 'semester_id' => $this->term->id]))
            ->assertOk()->assertSee("Pupil's Terminal Report")->assertSee('1st out of 2')->assertSee('BOATENG Akua');

        $this->get(route('students.report-card', ['student' => $kofi, 'semester_id' => $this->term->id]))->assertOk()->assertSee('Advanced');
        $this->get(route('scores.broadsheet', ['classGroup' => $this->basic4, 'semester_id' => $this->term->id]))->assertOk();

        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute('report-card.show', now()->addDay(), ['student' => $kofi->id, 'semester_id' => $this->term->id]);
        auth()->logout();
        $this->get($signed)->assertOk()->assertSee('ASANTE Kofi');
        $this->get(route('report-card.show', $kofi))->assertForbidden();
    }

    public function test_promotion_moves_ticked_pupils_and_completes_final_class(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $yaw = $this->pupil('MENSAH Yaw', $this->basic4);
        $basic5 = \App\Models\ClassGroup::create(['name' => 'Basic 5A', 'level' => 10]);

        $this->post(route('promotions.store'), ['class_group_id' => $this->basic4->id, 'target_class_group_id' => $basic5->id, 'student_ids' => [$kofi->id]])->assertRedirect();

        $this->assertSame($basic5->id, $kofi->fresh()->class_group_id);
        $this->assertSame(10, $kofi->fresh()->level);
        $this->assertSame($this->basic4->id, $yaw->fresh()->class_group_id);

        $jhs3 = \App\Models\ClassGroup::create(['name' => 'JHS 3A', 'level' => 14]);
        $leaver = $this->pupil('OSEI Kwame', $jhs3);
        $this->post(route('promotions.store'), ['class_group_id' => $jhs3->id, 'student_ids' => [$leaver->id], 'graduation_year' => 2027]);
        $this->assertSame('graduated', $leaver->fresh()->status);
    }
}

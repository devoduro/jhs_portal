<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\TerminalReportService;
use App\Services\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class TranscriptTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private $kofi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();

        // Last year: Kofi was in Basic 4A (two terms of scores).
        $lastYear = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31']);
        $t1 = Semester::create(['academic_year_id' => $lastYear->id, 'name' => 'First Term', 'semester_number' => 1, 'start_date' => '2025-09-08', 'end_date' => '2025-12-18']);
        $t2 = Semester::create(['academic_year_id' => $lastYear->id, 'name' => 'Second Term', 'semester_number' => 2, 'start_date' => '2026-01-10', 'end_date' => '2026-04-08']);

        $this->kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $mate = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $english4 = Subject::where('level', 9)->where('title', 'English Language')->first();
        TerminalReportService::saveScores($this->basic4, $english4, $t1, [$this->kofi->id => ['class_score' => 40, 'exam_score' => 40], $mate->id => ['class_score' => 30, 'exam_score' => 30]]);
        TerminalReportService::saveScores($this->basic4, $english4, $t2, [$this->kofi->id => ['class_score' => 45, 'exam_score' => 45], $mate->id => ['class_score' => 30, 'exam_score' => 30]]);

        // This year: promoted to Basic 5A.
        $basic5 = ClassGroup::create(['name' => 'Basic 5A', 'level' => 10]);
        $this->kofi->update(['class_group_id' => $basic5->id, 'level' => 10]);
        $english5 = Subject::where('level', 10)->where('title', 'English Language')->first();
        TerminalReportService::saveScores($basic5, $english5, $this->term, [$this->kofi->id => ['class_score' => 35, 'exam_score' => 35]]);
    }

    public function test_record_groups_scores_by_class_with_term_columns_and_averages(): void
    {
        $t = TranscriptService::forStudent($this->kofi);

        $this->assertSame([9, 10], $t['years']->pluck('level')->all());
        $basic4 = $t['years']->first();
        $this->assertSame('Basic 4A', $basic4['class']);
        $this->assertSame('2025/2026', $basic4['academic_year']);
        $this->assertCount(2, $basic4['terms']);
        $this->assertEquals(85, $basic4['subjects']->first()['average']);   // (80 + 90) / 2
        $this->assertSame('A', $basic4['subjects']->first()['grade']);
        $this->assertSame(1, $basic4['termSummaries']->first()['position']);
        $this->assertEquals(77.5, $t['overall']);                            // (85 + 70) / 2
    }

    public function test_level_range_limits_the_record(): void
    {
        $t = TranscriptService::forStudent($this->kofi, 9, 9);
        $this->assertSame([9], $t['years']->pluck('level')->all());
    }

    public function test_admin_prints_single_and_class_transcripts(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get(route('students.transcript', $this->kofi))
            ->assertOk()->assertSee('Academic Transcript')->assertSee('Basic 4A')->assertSee('Basic 5A')->assertSee('Basic 4 to Basic 5');

        $this->get(route('students.transcript', ['student' => $this->kofi, 'from' => 10, 'to' => 10]))
            ->assertOk()->assertSee('Basic 5A')->assertDontSee('Basic 4A');

        $this->get(route('transcripts.index'))->assertOk();
        $this->get(route('transcripts.bulk', ['class_group_id' => $this->basic4->id, 'from' => 6, 'to' => 11]))->assertOk()->assertSee('BOATENG Akua');
    }

    public function test_teachers_cannot_print_transcripts(): void
    {
        $this->actingAs($this->teacherUser())->get(route('students.transcript', $this->kofi))->assertForbidden();
    }

    public function test_restyled_fee_account_page_renders(): void
    {
        $this->actingAs($this->staff('Accountant'));
        $this->post(route('fees.charges.store', $this->kofi), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000]);
        $this->post(route('fees.payments.store', $this->kofi), ['semester_id' => $this->term->id, 'amount' => 250, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);

        $this->get(route('fees.show', $this->kofi))
            ->assertOk()
            ->assertSee('Owing Now')
            ->assertSee('GH₵ 750.00')
            ->assertSee('25% of this term paid')
            ->assertSee('Full balance 750.00')
            ->assertSee('Print Receipt');
    }
}

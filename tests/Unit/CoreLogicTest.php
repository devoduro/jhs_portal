<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\GradeScheme;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Services\BulkBillingService;
use App\Services\FeeLedgerService;
use App\Services\TerminalReportService;
use App\Support\FormLabel;
use App\Support\Greeting;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * The calculations the whole system leans on, tested without going through a screen: phone and
 * money formatting, class levels, fee balances and ledgers, term order, grading and score saving.
 */
class CoreLogicTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    // ------------------------------------------------------------- support

    public function test_phone_numbers_are_cleaned_to_the_local_format(): void
    {
        foreach ([
            '0241234567' => '0241234567', '024 123 4567' => '0241234567', '+233 24 123 4567' => '0241234567', '233241234567' => '0241234567',
            '241234567' => '0241234567', '(024) 123-4567' => '0241234567',
        ] as $input => $expected) {
            $this->assertSame($expected, Phone::clean($input), $input);
        }

        $this->assertNull(Phone::clean(null));
        $this->assertNull(Phone::clean('  '));
        $this->assertNull(Phone::clean('n/a'));
    }

    public function test_money_is_formatted_and_spelled_out_for_receipts(): void
    {
        $this->assertSame('GH₵ 1,234.50', Money::format(1234.5));
        $this->assertSame('GH₵ 0.00', Money::format(null));

        $this->assertSame('One Ghana Cedi Only', Money::words(1));
        $this->assertSame('Zero Ghana Cedis, Five Pesewas Only', Money::words(0.05));
        $this->assertSame('One Thousand One Hundred Ghana Cedis Only', Money::words(1100));
        $this->assertSame('Two Thousand and Fifty Ghana Cedis Only', Money::words(2050));
        $this->assertSame('One Hundred and Twenty-One Ghana Cedis, Fifty Pesewas Only', Money::words(121.50));
        $this->assertSame('Five Ghana Cedis Only', Money::words(-5));
    }

    public function test_greeting_uses_the_first_name_or_title_and_surname(): void
    {
        $this->assertSame('Ama', Greeting::shortName('Ama Owusu'));
        $this->assertSame('Mrs. Owusu', Greeting::shortName('Mrs. Grace Owusu'));
        $this->assertSame('Dr Mensah', Greeting::shortName('Dr Kofi Mensah (Head)'));
        $this->assertSame('', Greeting::shortName(null));
        $this->assertStringStartsWith('Good ', Greeting::salutation('Ama Owusu'));
    }

    public function test_class_levels_group_into_sections_and_promote_in_order(): void
    {
        $this->assertSame('Basic 4', FormLabel::label(9));
        $this->assertSame('Not set', FormLabel::label(null));
        $this->assertSame('preschool', FormLabel::section(5));
        $this->assertSame('primary', FormLabel::section(11));
        $this->assertSame('jhs', FormLabel::section(12));
        $this->assertTrue(FormLabel::isJhs(14));
        $this->assertFalse(FormLabel::isJhs(11));
        $this->assertSame('B4', FormLabel::shortCode(9));
        $this->assertSame('J2', FormLabel::shortCode(13));
        $this->assertSame('KG1', FormLabel::shortCode(4));

        $this->assertSame(10, FormLabel::next(9));
        $this->assertSame(12, FormLabel::next(11));          // Basic 6 -> JHS 1
        $this->assertNull(FormLabel::next(14));               // JHS 3 is final
        $this->assertTrue(FormLabel::isFinal(14));

        // A school without JHS 3 completes at JHS 2, and a level it dropped steps to the next one it runs.
        DB::table('settings')->updateOrInsert(['key' => 'levels_offered', 'category' => 'academic'], ['value' => '9,10,11,12,13', 'updated_at' => now()]);
        FormLabel::forgetOffered();
        $this->assertSame([9, 10, 11, 12, 13], array_keys(FormLabel::options()));
        $this->assertTrue(FormLabel::isFinal(13));
        $this->assertSame(9, FormLabel::next(5));
        FormLabel::forgetOffered();
    }

    // ---------------------------------------------------- fee balances & ledger

    private function pupilWithAccount(): array
    {
        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $year = $this->term->academic_year_id;
        $second = Semester::where('semester_number', 2)->first();

        StudentFeeCharge::create(['student_id' => $pupil->id, 'academic_year_id' => $year, 'semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000, 'notes' => 'Term bill']);
        StudentFeeCharge::create(['student_id' => $pupil->id, 'academic_year_id' => $year, 'semester_id' => $this->term->id, 'category' => 'discount', 'amount' => -100]);
        StudentFeeCharge::create(['student_id' => $pupil->id, 'academic_year_id' => $year, 'semester_id' => $second->id, 'category' => 'tuition', 'amount' => 1000, 'notes' => 'Term bill']);
        StudentArrear::create(['student_id' => $pupil->id, 'academic_year_id' => $year, 'amount' => 250]);
        StudentPayment::create(['student_id' => $pupil->id, 'academic_year_id' => $year, 'semester_id' => $this->term->id, 'amount' => 600, 'payment_method' => 'cash', 'payment_date' => now()->subDays(5)]);

        return [$pupil, $second];
    }

    public function test_a_pupils_balance_is_bills_plus_arrears_minus_payments(): void
    {
        [$pupil, $second] = $this->pupilWithAccount();

        $this->assertEquals(900, $pupil->billedAmount(null, $this->term));              // 1000 - 100 discount
        $this->assertEquals(1900, $pupil->billedAmount());
        $this->assertEquals(600, $pupil->paidAmount(null, $this->term));
        $this->assertEquals(0, $pupil->paidAmount(null, $second));
        $this->assertEquals(1550, $pupil->outstandingBalance());                         // 1900 + 250 - 600

        // What was owed before the second term: the first term's balance plus the arrears.
        $this->assertEquals(550, $pupil->balanceBroughtForward($second));               // 900 + 250 - 600
        $this->assertEquals(250, $pupil->balanceBroughtForward($this->term));           // arrears only

        // The positions used on the fee list agree, and a later term's bill never shows as brought forward.
        $position = FeeLedgerService::positions(collect([$pupil->id]), $this->term)[$pupil->id];
        $this->assertEquals(250, $position['brought_forward']);
        $this->assertEquals(550, FeeLedgerService::positions(collect([$pupil->id]), $second)[$pupil->id]['brought_forward']);

        // An overpayment shows as credit (negative balance).
        StudentPayment::create(['student_id' => $pupil->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $second->id, 'amount' => 2000, 'payment_method' => 'cash', 'payment_date' => now()]);
        $this->assertEquals(-450, $pupil->fresh()->outstandingBalance());
    }

    public function test_bulk_position_lookup_matches_the_per_pupil_figures(): void
    {
        [$pupil, $second] = $this->pupilWithAccount();
        $other = $this->pupil('BOATENG Akua', $this->basic4, 'Female');

        $positions = FeeLedgerService::positions(collect([$pupil->id, $other->id]), $this->term);

        $this->assertEquals($pupil->outstandingBalance(), $positions[$pupil->id]['balance']);
        $this->assertEquals(900, $positions[$pupil->id]['term_bill']);
        $this->assertEquals(600, $positions[$pupil->id]['term_paid']);
        $this->assertSame(['balance' => 0.0, 'brought_forward' => 0.0, 'term_bill' => 0.0, 'term_paid' => 0.0], $positions[$other->id]);
    }

    public function test_the_ledger_runs_a_balance_and_puts_bills_before_payments_on_the_same_day(): void
    {
        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $day = now()->toDateString();
        $this->term->update(['start_date' => $day]);

        StudentPayment::create(['student_id' => $pupil->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'amount' => 400, 'payment_method' => 'cash', 'payment_date' => $day]);
        StudentFeeCharge::create(['student_id' => $pupil->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000]);

        $ledger = FeeLedgerService::ledgerFor($pupil);           // newest first

        $this->assertCount(2, $ledger);
        $this->assertEquals(600, $ledger[0]['balance']);          // after the payment
        $this->assertEquals(1000, $ledger[1]['balance']);         // the bill came first
        $this->assertNotEmpty($ledger[0]['payment_id']);
    }

    public function test_term_billing_skips_billed_items_and_refreshes_on_request(): void
    {
        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        \App\Models\FeeStructure::create(['semester_id' => $this->term->id, 'academic_year_id' => $this->term->academic_year_id, 'level' => 9, 'category' => 'tuition', 'amount' => 1000]);
        \App\Models\FeeStructure::create(['semester_id' => $this->term->id, 'academic_year_id' => $this->term->academic_year_id, 'level' => 9, 'category' => 'feeding', 'amount' => 300]);

        $first = FeeLedgerService::billTerm($this->term, collect([$pupil]));
        $this->assertSame(2, $first['billed']);
        $this->assertEquals(1300, $first['total']);

        $again = FeeLedgerService::billTerm($this->term, collect([$pupil]));
        $this->assertSame(0, $again['billed']);
        $this->assertSame(2, $again['skipped']);

        \App\Models\FeeStructure::where('category', 'tuition')->update(['amount' => 1100]);
        $refresh = FeeLedgerService::billTerm($this->term, collect([$pupil]), ['tuition'], true);
        $this->assertSame(1, $refresh['updated']);
        $this->assertEquals(1400, $pupil->fresh()->billedAmount());

        // The plan shown before billing agrees with what billing would do.
        $newcomer = $this->pupil('NEW Pupil', $this->basic4);
        $plan = FeeLedgerService::termPlan($this->term);
        $this->assertSame(2, $plan['new_items']);
        $this->assertEquals(1400, $plan['new_total']);
        $this->assertSame(2, $plan['pupils']);
    }

    public function test_a_percentage_discount_has_nothing_to_take_a_percentage_of_when_unbilled(): void
    {
        $billed = $this->pupil('ASANTE Kofi', $this->basic4);
        $unbilled = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        StudentFeeCharge::create(['student_id' => $billed->id, 'academic_year_id' => $this->term->academic_year_id, 'semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000, 'notes' => 'Term bill']);

        $plan = BulkBillingService::plan($this->term, collect([$billed, $unbilled]), [
            'category' => BulkBillingService::discountCategory(), 'mode' => 'percent', 'amount' => 10, 'is_discount' => true, 'existing' => 'skip',
        ]);

        $rows = $plan['rows']->keyBy(fn ($r) => $r['student']->id);
        $this->assertEquals(-100, $rows[$billed->id]['amount']);
        $this->assertSame('add', $rows[$billed->id]['action']);
        $this->assertSame('nothing', $rows[$unbilled->id]['action']);
        $this->assertSame(1, $plan['count']);
        $this->assertEquals(-100, $plan['total']);
    }

    // ----------------------------------------------------------------- terms

    public function test_terms_follow_each_other_across_academic_years(): void
    {
        $third = Semester::where('semester_number', 3)->first();
        $second = Semester::where('semester_number', 2)->first();

        $this->assertSame($second->id, $this->term->nextTerm()->id);
        $this->assertTrue($third->isFinalTerm());
        $this->assertFalse($this->term->isFinalTerm());
        $this->assertNull($third->nextTerm());                                        // nothing after the last term yet

        $next = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-09-01', 'end_date' => '2028-07-31']);
        $firstOfNext = Semester::create(['academic_year_id' => $next->id, 'name' => 'First Term', 'semester_number' => 1, 'start_date' => '2027-09-01', 'end_date' => '2027-12-15']);
        $this->assertSame($firstOfNext->id, $third->fresh()->nextTerm()->id);

        $this->assertStringContainsString('2026/2027', $this->term->full_name);
    }

    // --------------------------------------------------------------- grading

    public function test_grades_come_from_the_scheme_for_the_class_section(): void
    {
        $primary = GradeScheme::forLevel(9);
        $this->assertNotNull($primary);

        $top = GradeScheme::gradeFor(95, 9);
        $bottom = GradeScheme::gradeFor(5, 9);
        $this->assertNotNull($top['grade']);
        $this->assertNotSame($top['grade'], $bottom['grade']);
        $this->assertNotEmpty($top['remark']);

        // A score between bands takes the highest band whose minimum it reaches.
        $band = $primary->bandForScore(64.9);
        $this->assertNotNull($band);
        $this->assertTrue($band->min_score <= 64.9);
        $this->assertEmpty($primary->grades->where('min_score', '>', $band->min_score)->where('min_score', '<=', 64.9));

        // JHS pupils are graded on their own (BECE-style) scale.
        $this->assertNotNull(GradeScheme::gradeFor(80, 12)['grade_point']);
    }

    public function test_positions_use_competition_ranking_with_shared_places(): void
    {
        $this->assertSame([1 => 1, 2 => 2, 3 => 2, 4 => 4], TerminalReportService::rank([1 => 90, 2 => 80, 3 => 80, 4 => 70]));
        $this->assertSame([], TerminalReportService::rank([]));
        $this->assertSame('1st', TerminalReportService::ordinal(1));
        $this->assertSame('11th', TerminalReportService::ordinal(11));
        $this->assertSame('22nd', TerminalReportService::ordinal(22));
        $this->assertSame('103rd', TerminalReportService::ordinal(103));
    }

    public function test_saving_scores_totals_grades_and_clears_blank_rows(): void
    {
        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();

        $saved = TerminalReportService::saveScores($this->basic4, $subject, $this->term, [$pupil->id => ['class_score' => 35.5, 'exam_score' => 40]]);
        $this->assertSame(1, $saved);

        $result = Result::where('student_id', $pupil->id)->first();
        $this->assertEquals(75.5, $result->score);
        $this->assertNotEmpty($result->grade);
        $this->assertSame($this->basic4->id, $result->class_group_id);

        // Re-saving updates the same row; a blank class score is not treated as zero.
        TerminalReportService::saveScores($this->basic4, $subject, $this->term, [$pupil->id => ['class_score' => '', 'exam_score' => 40]]);
        $this->assertSame(1, Result::count());
        $this->assertNull($result->fresh()->class_score);
        $this->assertEquals(40, $result->fresh()->score);

        // Both blank removes the result.
        TerminalReportService::saveScores($this->basic4, $subject, $this->term, [$pupil->id => ['class_score' => '', 'exam_score' => '']]);
        $this->assertSame(0, Result::count());
    }

    public function test_a_designed_score_sheet_sums_components_into_class_and_exam_scores(): void
    {
        AssessmentComponent::create(['level' => 9, 'label' => 'Exercises', 'type' => 'class', 'max_mark' => 20, 'position' => 0]);
        AssessmentComponent::create(['level' => 9, 'label' => 'Project', 'type' => 'class', 'max_mark' => 20, 'position' => 1]);
        AssessmentComponent::create(['level' => 9, 'label' => 'Exam', 'type' => 'exam', 'max_mark' => 60, 'position' => 2]);

        $sheet = AssessmentComponent::sheetFor(9);
        $this->assertSame(['Exercises', 'Project', 'Exam'], $sheet->pluck('label')->all());
        $this->assertTrue(AssessmentComponent::isConfigured(9));
        $this->assertFalse(AssessmentComponent::isConfigured(10));
        $this->assertSame(['class_score', 'exam_score'], AssessmentComponent::sheetFor(10)->pluck('key')->all());   // default two-column sheet

        $pupil = $this->pupil('ASANTE Kofi', $this->basic4);
        $subject = Subject::where('level', 9)->first();
        [$exercises, $project, $exam] = $sheet->pluck('key')->all();

        TerminalReportService::saveScores($this->basic4, $subject, $this->term, [$pupil->id => [$exercises => 15, $project => '', $exam => 50]]);
        $result = Result::first();
        $this->assertEquals(15, $result->class_score);              // only the entered class marks count
        $this->assertEquals(50, $result->exam_score);
        $this->assertEquals(65, $result->score);
        $this->assertSame(2, $result->componentScores()->count());

        // Clearing one box removes just that component's mark.
        TerminalReportService::saveScores($this->basic4, $subject, $this->term, [$pupil->id => [$exercises => '', $project => 10, $exam => 50]]);
        $this->assertSame(['Exam', 'Project'], $result->fresh()->componentScores()->with('component')->get()->pluck('component.label')->sort()->values()->all());
        $this->assertEquals(60, $result->fresh()->score);
    }

    public function test_the_jhs_aggregate_counts_the_core_subjects_plus_the_best_others(): void
    {
        $pupil = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');

        $this->assertNull($pupil->calculateAggregate($this->term->id));          // no results yet
        $this->assertNull($this->pupil('KID Basic', $this->basic4)->calculateAggregate($this->term->id));   // not a JHS pupil

        $subjects = Subject::where('level', 12)->get();
        $this->assertGreaterThanOrEqual(6, $subjects->count());
        foreach ($subjects as $subject) {
            TerminalReportService::saveScores($this->jhs1, $subject, $this->term, [$pupil->id => ['class_score' => 45, 'exam_score' => 45]]);
        }

        $aggregate = $pupil->fresh()->calculateAggregate($this->term->id);
        $this->assertNotNull($aggregate);
        $this->assertSame(6, $pupil->bestSixResults($this->term->id)->count());
        // Six identical grade values of the same grade.
        $gradeValue = (int) GradeScheme::gradeFor(90, 12)['grade_point'];
        $this->assertSame($gradeValue * 6, $aggregate);
    }
}

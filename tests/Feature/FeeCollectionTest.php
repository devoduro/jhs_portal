<?php

namespace Tests\Feature;

use App\Models\FeeStructure;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class FeeCollectionTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function setTermFees(): void
    {
        $this->actingAs($this->staff('Accountant'))->post(route('fee-structures.save'), [
            'semester_id' => $this->term->id,
            'amounts' => [
                'tuition' => [9 => 1200, 12 => 1500],
                'feeding' => [9 => 450, 12 => 450],
                'books' => [12 => 300],
            ],
        ])->assertRedirect();
    }

    public function test_fee_schedule_is_saved_per_class_level_and_blank_cells_remove_items(): void
    {
        $this->setTermFees();
        $this->assertSame(5, FeeStructure::where('semester_id', $this->term->id)->count());

        $this->post(route('fee-structures.save'), [
            'semester_id' => $this->term->id,
            'amounts' => ['books' => [12 => '']],
        ]);

        $this->assertSame(4, FeeStructure::where('semester_id', $this->term->id)->count());
    }

    public function test_billing_a_term_bills_each_pupil_by_class_and_never_twice(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->setTermFees();

        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id])->assertRedirect();
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id])->assertRedirect();

        $this->assertEquals(1650, $kofi->billedAmount(null, $this->term));
        $this->assertEquals(2250, $esi->billedAmount(null, $this->term));
        $this->assertSame(5, StudentFeeCharge::count());
    }

    public function test_payment_produces_receipt_and_correct_running_balance(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->setTermFees();
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
        StudentArrear::create(['student_id' => $kofi->id, 'academic_year_id' => $this->term->academic_year_id, 'amount' => 250]);

        $this->post(route('fees.payments.store', $kofi), [
            'semester_id' => $this->term->id,
            'amount' => 1000,
            'payment_method' => 'mobile_money',
            'bank' => 'MTN',
            'payment_date' => now()->toDateString(),
            'paid_by' => 'Mr. Asante',
        ])->assertRedirect(route('fees.show', ['student' => $kofi->id, 'semester_id' => $this->term->id]))
            ->assertSessionHas('receipt_url');

        $payment = StudentPayment::first();
        $this->assertMatchesRegularExpression('/^RCT-\d{4}-\d{6}$/', $payment->receipt_number);
        $this->assertEquals(900, $kofi->fresh()->outstandingBalance());       // 1650 + 250 - 1000
        $this->assertEquals(250, $kofi->fresh()->balanceBroughtForward($this->term));

        $this->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('One Thousand Ghana Cedis Only')
            ->assertSee('900.00');
    }

    public function test_invalid_payments_are_rejected(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->actingAs($this->staff('Accountant'));

        foreach ([['amount' => -5], ['amount' => 100, 'payment_date' => now()->addDay()->toDateString()], ['amount' => 100, 'payment_method' => 'bitcoin']] as $bad) {
            $this->post(route('fees.payments.store', $kofi), $bad + [
                'semester_id' => $this->term->id, 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
            ])->assertSessionHasErrors();
        }

        $this->assertSame(0, StudentPayment::count());
    }

    public function test_one_off_item_can_be_added_to_a_single_pupil(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);

        $this->actingAs($this->staff('Accountant'))->post(route('fees.charges.store', $kofi), [
            'semester_id' => $this->term->id, 'category' => 'transport', 'amount' => 300, 'notes' => 'Bus',
        ])->assertRedirect();

        $this->assertEquals(300, $kofi->outstandingBalance());
    }

    public function test_searching_for_a_single_pupil_opens_their_fee_account(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('BOATENG Akua', $this->basic4, 'Female');

        $this->actingAs($this->staff('Accountant'))
            ->get(route('fees.index', ['search' => 'Asante']))
            ->assertRedirect(route('fees.show', ['student' => $kofi->id, 'semester_id' => $this->term->id]));

        $this->get(route('fees.index', ['search' => 'Basic']))->assertOk();
    }

    public function test_fee_pages_and_reports_render(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->setTermFees();
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
        $this->post(route('fees.payments.store', $kofi), ['semester_id' => $this->term->id, 'amount' => 500, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);

        foreach ([
            route('fees.index'), route('fees.show', $kofi), route('fees.print', $kofi), route('fee-structures.index'),
            route('fees.billing'), route('fees.charges.index'), route('fees.arrears.index'), route('fees.report'),
            route('fees.report.print', ['semester_id' => $this->term->id]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_teacher_cannot_reach_fees(): void
    {
        $this->actingAs($this->teacherUser())->get(route('fees.index'))->assertForbidden();
    }

    public function test_fee_list_status_tabs_count_and_filter_pupils(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->actingAs($this->staff('Accountant'));
        foreach ([$kofi, $akua] as $pupil) {
            $this->post(route('fees.charges.store', $pupil), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 500]);
        }
        $this->post(route('fees.payments.store', $akua), ['semester_id' => $this->term->id, 'amount' => 500, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);

        $this->get(route('fees.index'))
            ->assertOk()
            ->assertViewHas('statusCounts', ['all' => 2, 'debtor' => 1, 'settled' => 1, 'creditor' => 0])
            ->assertSee('50% of term bills collected');

        $this->get(route('fees.index', ['status' => 'debtors']))->assertSee('ASANTE Kofi')->assertDontSee('BOATENG Akua');
        $this->get(route('fees.index', ['status' => 'settled']))->assertSee('BOATENG Akua')->assertDontSee('ASANTE Kofi');
    }

    public function test_billed_items_page_filters_by_item_and_type_and_bulk_removes(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->setTermFees();
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
        $this->post(route('fees.charges.store', $kofi), ['semester_id' => $this->term->id, 'category' => 'transport', 'amount' => 300, 'notes' => 'Bus']);
        $this->post(route('fees.charges.store', $akua), ['semester_id' => $this->term->id, 'category' => 'uniform', 'amount' => -50, 'notes' => 'Sibling discount']);

        $this->get(route('fees.charges.index'))
            ->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['items'] === 6 && $s['individual'] === 2 && abs($s['discounts'] + 50) < 0.01)
            ->assertSee('Transport / Bus Fee');

        $this->get(route('fees.charges.index', ['category' => 'transport']))->assertSee('ASANTE Kofi')->assertDontSee('BOATENG Akua');
        $this->get(route('fees.charges.index', ['type' => 'discount']))->assertSee('Sibling discount')->assertDontSee('ASANTE Kofi');

        $ids = StudentFeeCharge::where('notes', '!=', 'Term bill')->pluck('id')->all();
        $this->from(route('fees.charges.index', ['type' => 'individual']))
            ->post(route('fees.charges.bulk-destroy'), ['charge_ids' => $ids])
            ->assertRedirect(route('fees.charges.index', ['type' => 'individual']));
        $this->assertSame(4, StudentFeeCharge::count());
    }

    public function test_fee_schedule_page_shows_expected_income(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->setTermFees();   // Basic 4: 1650, JHS 1: 2250

        $this->get(route('fee-structures.index'))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => abs($s['expected'] - (1650 * 2 + 2250)) < 0.01 && $s['items'] === 3 && $s['classes'] === 2)
            ->assertSee('Expected Income')
            ->assertSee('GH₵ 5,550.00');
    }
}

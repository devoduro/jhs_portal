<?php

namespace Tests\Feature;

use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class BulkFeesTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function billTermFees(): void
    {
        $this->actingAs($this->staff('Accountant'))->post(route('fee-structures.save'), [
            'semester_id' => $this->term->id,
            'amounts' => ['tuition' => [9 => 1000, 12 => 1500], 'feeding' => [9 => 200, 12 => 200]],
        ]);
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
    }

    public function test_term_billing_page_previews_what_is_still_to_be_billed(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->actingAs($this->staff('Accountant'))->post(route('fee-structures.save'), [
            'semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1000, 12 => 1500]],
        ]);

        $this->get(route('fees.billing'))->assertOk()
            ->assertViewHas('plan', fn ($p) => $p['new_items'] === 2 && abs($p['new_total'] - 2500) < 0.01 && $p['pupils'] === 2);

        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id]);
        $this->get(route('fees.billing'))->assertViewHas('plan', fn ($p) => $p['new_items'] === 0)->assertSee('All billed');
    }

    public function test_bulk_bill_previews_then_bills_selected_classes_only(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->actingAs($this->staff('Accountant'));

        $form = ['semester_id' => $this->term->id, 'target' => 'classes', 'class_group_ids' => [$this->basic4->id], 'category' => 'transport',
            'mode' => 'fixed', 'amount' => 120, 'existing' => 'skip', 'notes' => 'Bus'];

        $this->post(route('fees.bulk-bill.preview'), $form)->assertOk()->assertSee('ASANTE Kofi')->assertDontSee('ADJEI Esi');
        $this->assertSame(0, StudentFeeCharge::count());   // nothing saved by the preview

        $this->post(route('fees.bulk-bill.store'), $form)->assertRedirect();
        $this->assertEquals(120, $kofi->billedAmount());
        $this->assertEquals(0, $esi->billedAmount());

        // Running it again leaves existing items alone; "replace" updates them.
        $this->post(route('fees.bulk-bill.store'), $form)->assertSessionHas('error');
        $this->post(route('fees.bulk-bill.store'), array_replace($form, ['amount' => 150, 'existing' => 'overwrite']));
        $this->assertEquals(150, $kofi->fresh()->billedAmount());
        $this->assertSame(1, StudentFeeCharge::count());
    }

    public function test_bulk_percentage_discount_is_taken_off_each_pupils_own_bill(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->billTermFees();   // Kofi 1200, Esi 1700

        $this->post(route('fees.bulk-bill.store'), [
            'semester_id' => $this->term->id, 'target' => 'pupils', 'student_ids' => [$kofi->id, $esi->id], 'is_discount' => 1,
            'mode' => 'percent', 'amount' => 10, 'base_category' => 'tuition', 'existing' => 'skip', 'notes' => 'Staff child',
        ])->assertRedirect();

        $this->assertEquals(1200 - 100, $kofi->fresh()->outstandingBalance());
        $this->assertEquals(1700 - 150, $esi->fresh()->outstandingBalance());
        $this->assertSame(2, StudentFeeCharge::where('category', 'discount')->count());
    }

    public function test_bulk_bill_never_overwrites_an_item_from_the_fee_schedule(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->billTermFees();

        $this->post(route('fees.bulk-bill.store'), [
            'semester_id' => $this->term->id, 'target' => 'all', 'category' => 'tuition', 'mode' => 'fixed', 'amount' => 5, 'existing' => 'overwrite',
        ])->assertSessionHas('error');

        $this->assertEquals(1200, $kofi->fresh()->billedAmount());
    }

    public function test_bulk_bill_validates_its_form(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->actingAs($this->staff('Accountant'));

        $base = ['semester_id' => $this->term->id, 'target' => 'classes', 'category' => 'transport', 'mode' => 'fixed', 'amount' => 50, 'existing' => 'skip'];
        $this->post(route('fees.bulk-bill.preview'), $base)->assertSessionHasErrors('class_group_ids');
        $this->post(route('fees.bulk-bill.preview'), array_replace($base, ['target' => 'all', 'mode' => 'percent', 'amount' => 150]))->assertSessionHasErrors('amount');
        $this->post(route('fees.bulk-bill.preview'), array_replace($base, ['target' => 'all', 'amount' => 0]))->assertSessionHasErrors('amount');
        $this->post(route('fees.bulk-bill.preview'), array_replace($base, ['target' => 'all', 'category' => 'nonsense']))->assertStatus(422);
    }

    public function test_class_payment_sheet_records_many_payments_with_receipts(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->billTermFees();

        $this->get(route('fees.collect', ['class_group_id' => $this->basic4->id]))->assertOk()
            ->assertSee('ASANTE Kofi')->assertSee('BOATENG Akua')->assertDontSee('ADJEI Esi');

        $form = ['semester_id' => $this->term->id, 'class_group_id' => $this->basic4->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'mobile_money', 'bank' => 'MTN',
            'rows' => [$kofi->id => ['amount' => 500, 'reference' => 'MP1'], $akua->id => ['amount' => '', 'reference' => ''], $esi->id => ['amount' => 999]]];

        $this->post(route('fees.collect.store'), $form)->assertRedirect()->assertSessionHas('receipts');

        // Only Kofi paid; Esi is not on Basic 4's roll so her box is ignored.
        $this->assertSame(1, StudentPayment::count());
        $payment = StudentPayment::first();
        $this->assertSame($kofi->id, $payment->student_id);
        $this->assertSame('mobile_money', $payment->payment_method);
        $this->assertMatchesRegularExpression('/^RCT-\d{4}-\d{6}$/', $payment->receipt_number);
        $this->assertEquals(700, $kofi->fresh()->outstandingBalance());

        // Submitting the same sheet again straight away is treated as a double-click.
        $this->post(route('fees.collect.store'), $form);
        $this->assertSame(1, StudentPayment::count());

        $this->post(route('fees.collect.store'), array_replace($form, ['rows' => [$kofi->id => ['amount' => '']]]))->assertSessionHas('error');
        $this->post(route('fees.collect.store'), array_replace($form, ['payment_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('payment_date');
    }

    public function test_payment_upload_checks_then_records_and_skips_duplicate_references(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $this->billTermFees();
        $this->actingAs($this->staff('Accountant'));

        $csv = implode("\n", [
            'index_number,amount,date,method,bank,reference,paid_by,term',
            "{$kofi->index_number},400," . now()->toDateString() . ',MoMo,MTN,TX100,Mr Asante,',
            "{$akua->index_number},250," . now()->toDateString() . ',cash,,,,',
            'NOPE/0000,100,' . now()->toDateString() . ',cash,,,,',
            "{$akua->index_number},-5," . now()->toDateString() . ',cash,,,,',
        ]);
        $file = fn () => UploadedFile::fake()->createWithContent('payments.csv', $csv);

        // Check-only run saves nothing.
        $this->post(route('fees.payments.import'), ['excel_file' => $file(), 'dry_run' => 1])->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(0, StudentPayment::count());

        $this->post(route('fees.payments.import'), ['excel_file' => $file()])->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(2, StudentPayment::count());
        $this->assertSame('mobile_money', StudentPayment::where('student_id', $kofi->id)->value('payment_method'));
        $this->assertEquals(800, $kofi->fresh()->outstandingBalance());     // 1200 - 400

        // Uploading the same statement again: Kofi's TX100 is recognised as already recorded.
        $this->post(route('fees.payments.import'), ['excel_file' => $file()]);
        $this->assertSame(2 + 1, StudentPayment::count());                  // only Akua's reference-less row repeats

        $this->get(route('fees.payments.upload'))->assertOk()->assertSee('Upload Payments');
        $this->get(route('fees.payments.template'))->assertOk();
    }

    public function test_remind_everyone_owing_reaches_only_debtors_in_the_filtered_list(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->billTermFees();
        $this->actingAs($this->staff('Super Admin'));

        $sms = \Mockery::mock(\App\Services\SmsReceiptService::class);
        $sms->shouldReceive('sendReminder')->once()->withArgs(fn ($student) => $student->id === $kofi->id)->andReturn(true);
        $this->app->instance(\App\Services\SmsReceiptService::class, $sms);

        $this->post(route('fees.reminders'), ['all_owing' => 1, 'class_group_id' => $this->basic4->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->post(route('fees.reminders'), [])->assertSessionHasErrors('student_ids');
    }

    public function test_bulk_fee_pages_render_and_teachers_are_locked_out(): void
    {
        $this->pupil('ASANTE Kofi', $this->basic4);
        $this->actingAs($this->staff('Accountant'));
        foreach ([route('fees.bulk-bill'), route('fees.collect'), route('fees.payments.upload')] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->teacherUser());
        foreach ([route('fees.bulk-bill'), route('fees.collect'), route('fees.payments.upload')] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}

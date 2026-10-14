<?php

namespace Tests\Feature;

use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class FeesImportsAndSetupTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Accountant'));
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('upload.csv', $content);
    }

    public function test_arrears_upload_works_with_the_downloadable_template_headings(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $year = $this->term->academicYear->name;

        $this->get(route('fees.arrears.template'))->assertOk();
        $this->get(route('fees.arrears.upload'))->assertOk();

        $sheet = "admission_number,academic_year,amount,notes\n"
            . "{$kofi->index_number},{$year},350,Unpaid third term\n"
            . "{$esi->index_number},{$year},-120,Overpayment\n"
            . "NOPE/1,{$year},50,\n"
            . "{$kofi->index_number},1999/2000,50,\n"
            . "{$esi->index_number},{$year},0,";

        $this->post(route('fees.arrears.import'), ['excel_file' => $this->csv($sheet)])->assertRedirect(route('fees.arrears.index'));

        $this->assertEquals(350, $kofi->arrears()->sum('amount'));
        $this->assertEquals(-120, $esi->arrears()->sum('amount'));
        $this->assertEquals(350, $kofi->outstandingBalance());
        $this->assertSame(2, StudentArrear::count());                     // unknown pupil, unknown year and zero rows are skipped

        // Uploading the same year again replaces, it does not stack.
        $this->post(route('fees.arrears.import'), ['excel_file' => $this->csv("admission_number,academic_year,amount\n{$kofi->index_number},{$year},400")]);
        $this->assertEquals(400, $kofi->arrears()->sum('amount'));

        $this->get(route('fees.arrears.index', ['status' => 'creditor']))->assertOk()->assertSee('ADJEI Esi');
        $arrear = $kofi->arrears()->first();
        $this->delete(route('fees.arrears.destroy', $arrear))->assertRedirect();
        $this->post(route('fees.arrears.bulk-destroy'), ['arrear_ids' => $esi->arrears()->pluck('id')->all()])->assertRedirect();
        $this->assertSame(0, StudentArrear::count());
        $this->post(route('fees.arrears.bulk-destroy'), [])->assertSessionHas('error');
    }

    public function test_billed_item_upload_adds_updates_and_rejects_bad_rows(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $year = $this->term->academicYear->name;

        $this->get(route('fees.charges.template'))->assertOk();
        $this->get(route('fees.charges.upload'))->assertOk();

        $sheet = "index_number,category,amount,academic_year,term,notes\n"
            . "{$kofi->index_number},transport,450,{$year},First Term,Bus\n"
            . "{$kofi->index_number},unknown-item,50,{$year},First Term,\n"
            . "{$kofi->index_number},uniform,-50,{$year},Fourth Term,\n"
            . "NOPE/1,uniform,50,{$year},First Term,";

        $this->post(route('fees.charges.import'), ['excel_file' => $this->csv($sheet)])->assertRedirect(route('fees.charges.index'));

        $this->assertSame(1, StudentFeeCharge::count());
        $this->assertEquals(450, $kofi->billedAmount(null, $this->term));

        $this->post(route('fees.charges.import'), ['excel_file' => $this->csv("index_number,category,amount,academic_year,term\n{$kofi->index_number},transport,500,{$year},First Term")]);
        $this->assertSame(1, StudentFeeCharge::count());
        $this->assertEquals(500, $kofi->billedAmount(null, $this->term));
    }

    public function test_single_item_billing_validates_and_a_discount_is_negative(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $bill = ['semester_id' => $this->term->id, 'category' => 'transport', 'amount' => 300];

        $this->post(route('fees.charges.store', $kofi), array_replace($bill, ['amount' => 0]))->assertSessionHasErrors('amount');
        $this->post(route('fees.charges.store', $kofi), array_replace($bill, ['category' => 'nonsense']))->assertSessionHasErrors('category');
        $this->post(route('fees.charges.store', $kofi), array_replace($bill, ['semester_id' => 9999]))->assertSessionHasErrors('semester_id');

        $this->post(route('fees.charges.store', $kofi), $bill)->assertRedirect();
        $this->post(route('fees.charges.store', $kofi), ['category' => 'uniform', 'amount' => -40] + $bill)->assertRedirect();
        $this->assertEquals(300 - 40, $kofi->fresh()->outstandingBalance());

        $charge = StudentFeeCharge::where('category', 'transport')->first();
        $this->delete(route('fees.charges.destroy', $charge))->assertRedirect();
        $this->assertEquals(-40, $kofi->fresh()->outstandingBalance());
    }

    public function test_fee_items_can_be_added_and_only_unused_unprotected_ones_removed(): void
    {
        $this->post(route('fees.categories.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('fees.categories.store'), ['name' => 'Library Fee'])->assertSessionHas('success');
        $this->post(route('fees.categories.store'), ['name' => 'Library Fee'])->assertSessionHasErrors('name');

        $library = FeeCategory::where('slug', 'library-fee')->first();
        $this->assertNotNull($library);

        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['library-fee' => [9 => 25]]]);
        $this->delete(route('fees.categories.destroy', $library))->assertSessionHas('error');          // used in the schedule

        FeeStructure::query()->delete();
        $this->delete(route('fees.categories.destroy', $library))->assertSessionHas('success');

        $tuition = FeeCategory::where('slug', 'tuition')->first();
        $this->delete(route('fees.categories.destroy', $tuition))->assertSessionHas('error');           // protected
    }

    public function test_fee_schedule_save_validates_copies_and_ignores_unknown_items_and_levels(): void
    {
        $this->post(route('fee-structures.save'), ['semester_id' => 9999])->assertSessionHasErrors('semester_id');
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => -5]]])->assertSessionHasErrors();

        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => [
            'tuition' => [9 => 1200, 999 => 50], 'not-an-item' => [9 => 10],
        ]])->assertRedirect();
        $this->assertSame(1, FeeStructure::count());                      // unknown level and unknown item dropped

        $next = Semester::where('semester_number', 2)->first();
        $this->post(route('fee-structures.copy'), ['semester_id' => $next->id, 'from_semester_id' => $this->term->id])->assertRedirect();
        $this->assertEquals(1200, FeeStructure::where('semester_id', $next->id)->value('amount'));

        $this->post(route('fee-structures.copy'), ['semester_id' => $this->term->id, 'from_semester_id' => $this->term->id])->assertSessionHasErrors('from_semester_id');
        $empty = Semester::where('semester_number', 3)->first();
        $this->post(route('fee-structures.copy'), ['semester_id' => $next->id, 'from_semester_id' => $empty->id])->assertSessionHas('error');
    }

    public function test_billing_a_single_pupil_or_chosen_classes_only(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female');
        $esi = $this->pupil('ADJEI Esi', $this->jhs1, 'Female');
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1000, 12 => 1500]]]);

        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id, 'student_id' => $kofi->id])->assertRedirect();
        $this->assertEquals(1000, $kofi->billedAmount());
        $this->assertEquals(0, $akua->billedAmount());

        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id, 'class_group_ids' => [$this->jhs1->id]])->assertRedirect();
        $this->assertEquals(1500, $esi->billedAmount());
        $this->assertEquals(0, $akua->billedAmount());

        // Re-pricing: the schedule changes, "update" refreshes only when asked.
        $this->post(route('fee-structures.save'), ['semester_id' => $this->term->id, 'amounts' => ['tuition' => [9 => 1100]]]);
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id, 'class_group_ids' => [$this->basic4->id]]);
        $this->assertEquals(1000, $kofi->fresh()->billedAmount());                       // already billed: untouched
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id, 'class_group_ids' => [$this->basic4->id], 'refresh' => 1]);
        $this->assertEquals(1100, $kofi->fresh()->billedAmount());
        $this->assertEquals(1100, $akua->fresh()->billedAmount());

        // A class with nobody in it or no fees set bills nothing and says so.
        $this->post(route('fees.billing.store'), ['semester_id' => $this->term->id, 'class_group_ids' => [$this->jhs1->id], 'categories' => ['feeding']])->assertSessionHas('error');
    }

    public function test_fee_payments_can_be_edited_and_removed_and_the_ledger_follows(): void
    {
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->post(route('fees.charges.store', $kofi), ['semester_id' => $this->term->id, 'category' => 'tuition', 'amount' => 1000]);
        $this->post(route('fees.payments.store', $kofi), ['semester_id' => $this->term->id, 'amount' => 400, 'payment_method' => 'cash', 'payment_date' => now()->toDateString()]);
        $payment = $kofi->payments()->first();

        $this->put(route('fees.payments.update', $payment), ['semester_id' => $this->term->id, 'amount' => 600, 'payment_method' => 'bank_transfer', 'bank' => 'GCB', 'payment_date' => now()->toDateString()])->assertRedirect();
        $this->assertEquals(400, $kofi->fresh()->outstandingBalance());
        $this->assertSame('bank_transfer', $payment->fresh()->payment_method);

        $this->delete(route('fees.payments.destroy', $payment))->assertRedirect();
        $this->assertEquals(1000, $kofi->fresh()->outstandingBalance());
    }
}

<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\GradeScheme;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\StudentPayment;
use App\Support\FormLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class SettingsAndTermsModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->actingAs($this->staff('Super Admin'));
    }

    // ----------------------------------------------------------------- terms

    private function termForm(array $override = []): array
    {
        return $override + ['name' => 'Fourth Term', 'academic_year_id' => $this->term->academic_year_id, 'semester_number' => 3,
            'start_date' => '2027-05-01', 'end_date' => '2027-07-30', 'attendance_days' => 60];
    }

    public function test_terms_are_validated_unique_per_year_and_set_current_keeps_one_current_term_and_year(): void
    {
        $this->get(route('semesters.index'))->assertOk();
        $this->get(route('semesters.create'))->assertOk();

        $this->post(route('semesters.store'), $this->termForm(['end_date' => '2027-04-01']))->assertSessionHasErrors('end_date');
        $this->post(route('semesters.store'), $this->termForm(['semester_number' => 4]))->assertSessionHasErrors('semester_number');
        $this->post(route('semesters.store'), $this->termForm(['semester_number' => 3]))->assertSessionHasErrors('semester_number');    // Third Term exists

        $next = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-09-01', 'end_date' => '2028-07-31']);
        $this->post(route('semesters.store'), $this->termForm(['academic_year_id' => $next->id, 'semester_number' => 1, 'name' => 'First Term']))->assertRedirect(route('semesters.index'));
        $newTerm = Semester::where('academic_year_id', $next->id)->first();

        $this->put(route('semesters.update', $newTerm), $this->termForm(['academic_year_id' => $next->id, 'semester_number' => 1, 'name' => 'Term One']))->assertRedirect();
        $this->assertSame('Term One', $newTerm->fresh()->name);
        $this->get(route('semesters.edit', $newTerm))->assertOk();
        $this->get(route('semesters.results', $newTerm))->assertOk();

        $this->put(route('semesters.set-current', $newTerm))->assertRedirect();
        $this->assertSame([$newTerm->id], Semester::where('is_current', true)->pluck('id')->all());
        $this->assertSame([$next->id], AcademicYear::where('is_current', true)->pluck('id')->all());
    }

    public function test_deleting_a_term_is_blocked_when_it_is_current_or_has_records(): void
    {
        $spare = Semester::where('semester_number', 3)->first();
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);

        $this->delete(route('semesters.destroy', $this->term))->assertSessionHas('error');          // current term
        $this->assertNotNull(Semester::find($this->term->id));

        $second = Semester::where('semester_number', 2)->first();
        StudentPayment::create(['student_id' => $kofi->id, 'academic_year_id' => $second->academic_year_id, 'semester_id' => $second->id, 'amount' => 50, 'payment_method' => 'cash', 'payment_date' => now()]);
        $this->delete(route('semesters.destroy', $second))->assertSessionHas('error');              // has a payment
        $this->assertNotNull(Semester::find($second->id));

        $this->delete(route('semesters.destroy', $spare))->assertSessionHas('success');             // nothing attached (this used to crash)
        $this->assertNull(Semester::find($spare->id));
    }

    // -------------------------------------------------------- academic years

    public function test_academic_years_are_created_with_terms_and_guarded_on_delete(): void
    {
        $this->get(route('settings.academic-years'))->assertOk();
        $this->get(route('settings.academic-years.create'))->assertOk();

        $form = ['name' => '2027/2028', 'start_date' => '2027-09-01', 'end_date' => '2028-07-31', 'is_current' => 1, 'semesters' => [
            ['semester_number' => 1, 'name' => 'First Term', 'start_date' => '2027-09-01', 'end_date' => '2027-12-15'],
            ['semester_number' => 2, 'name' => 'Second Term', 'start_date' => '2028-01-10', 'end_date' => '2028-04-05'],
        ]];

        $this->post(route('settings.academic-years.store'), ['name' => '2026/2027'] + $form)->assertSessionHasErrors('name');   // duplicate
        $this->post(route('settings.academic-years.store'), array_replace($form, ['semesters' => []]))->assertSessionHasErrors('semesters');
        $this->post(route('settings.academic-years.store'), $form)->assertRedirect(route('settings.academic-years'));

        $year = AcademicYear::where('name', '2027/2028')->first();
        $this->assertSame(2, $year->semesters()->count());
        $this->assertSame([$year->id], AcademicYear::where('is_current', true)->pluck('id')->all());

        $this->get(route('settings.academic-years.edit', $year))->assertOk();
        $this->put(route('settings.academic-years.update', $year), ['name' => '2027/28', 'start_date' => '2027-09-01', 'end_date' => '2028-07-31'])->assertRedirect();
        $this->assertSame('2027/28', $year->fresh()->name);

        // Making the previous year current again also moves the current term into it.
        $old = AcademicYear::where('name', '2026/2027')->first();
        $this->put(route('settings.academic-years.set-current', $old))->assertRedirect();
        $this->assertSame($old->id, Semester::where('is_current', true)->first()->academic_year_id);
        $this->assertSame(1, Semester::where('is_current', true)->count());

        $this->delete(route('settings.academic-years.destroy', $old))->assertSessionHas('error');   // current year
        $this->delete(route('settings.academic-years.destroy', $year))->assertSessionHas('success');
        $this->assertNull(AcademicYear::find($year->id));
    }

    public function test_an_academic_year_with_payments_cannot_be_deleted(): void
    {
        $year = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31']);
        $term = Semester::create(['academic_year_id' => $year->id, 'name' => 'First Term', 'semester_number' => 1, 'start_date' => '2025-09-01', 'end_date' => '2025-12-15']);
        $kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        StudentPayment::create(['student_id' => $kofi->id, 'academic_year_id' => $year->id, 'semester_id' => $term->id, 'amount' => 50, 'payment_method' => 'cash', 'payment_date' => now()]);

        $this->delete(route('settings.academic-years.destroy', $year))->assertSessionHas('error');
        $this->assertNotNull(AcademicYear::find($year->id));
        $this->assertSame(1, StudentPayment::count());
    }

    // ---------------------------------------------------------- grade schemes

    private function scheme(array $grades, array $override = []): array
    {
        return $override + ['name' => 'Test Scale', 'section' => 'primary', 'grades' => $grades];
    }

    public function test_grade_schemes_need_a_band_from_zero_and_no_duplicate_starts(): void
    {
        $this->get(route('settings.grade-schemes'))->assertOk();
        $this->get(route('settings.grade-schemes.create'))->assertOk();

        $noZero = [['grade' => 'A', 'min_score' => 50, 'grade_value' => 1, 'remark' => 'Good'], ['grade' => 'B', 'min_score' => 70, 'grade_value' => 2]];
        $this->post(route('settings.grade-schemes.store'), $this->scheme($noZero))->assertSessionHasErrors('grades');

        $dupes = [['grade' => 'A', 'min_score' => 0, 'grade_value' => 1], ['grade' => 'B', 'min_score' => 0, 'grade_value' => 2]];
        $this->post(route('settings.grade-schemes.store'), $this->scheme($dupes))->assertSessionHasErrors('grades');

        $ok = [['grade' => 'P', 'min_score' => 50, 'grade_value' => 1, 'remark' => 'Pass'], ['grade' => 'F', 'min_score' => 0, 'grade_value' => 2, 'remark' => 'Fail']];
        $this->post(route('settings.grade-schemes.store'), $this->scheme($ok, ['section' => 'preschool', 'name' => 'KG Scale']))->assertRedirect();
        $scheme = GradeScheme::where('name', 'KG Scale')->first();
        $this->assertSame(2, $scheme->grades()->count());

        $this->get(route('settings.grade-schemes.edit', $scheme))->assertOk();
        $this->put(route('settings.grade-schemes.update', $scheme), $this->scheme($noZero, ['name' => 'KG Scale']))->assertSessionHasErrors('grades');
        $this->put(route('settings.grade-schemes.update', $scheme), $this->scheme($ok + [2 => ['grade' => 'M', 'min_score' => 80, 'grade_value' => 0]], ['name' => 'KG Scale 2', 'section' => 'preschool']))->assertRedirect();
        $this->assertSame(3, $scheme->fresh()->grades()->count());
    }

    public function test_default_scheme_cannot_be_deleted_and_default_is_switched_cleanly(): void
    {
        $default = GradeScheme::where('is_default', true)->first();
        $other = GradeScheme::where('is_default', false)->first() ?? GradeScheme::create(['name' => 'Other', 'is_default' => false]);

        $this->delete(route('settings.grade-schemes.destroy', $default))->assertSessionHas('error');
        $this->assertNotNull(GradeScheme::find($default->id));

        $this->patch(route('settings.grade-schemes.set-default', $other))->assertRedirect();
        $this->assertSame([$other->id], GradeScheme::where('is_default', true)->pluck('id')->all());

        $this->delete(route('settings.grade-schemes.destroy', $default))->assertSessionHas('success');
    }

    // -------------------------------------------------- settings: school/system

    public function test_school_settings_validate_the_score_split_and_levels(): void
    {
        $this->get(route('settings.index'))->assertOk();
        $this->get(route('settings.school'))->assertOk();

        $base = ['levels' => [9, 10, 11], 'class_score_max' => 40, 'exam_score_max' => 60, 'institution_code' => 'fen', 'head_title' => 'Headmistress', 'report_show_fees' => 1];

        $this->put(route('settings.school.update'), array_replace($base, ['exam_score_max' => 50]))->assertSessionHasErrors('class_score_max');   // 90 != 100
        $this->put(route('settings.school.update'), array_replace($base, ['levels' => []]))->assertSessionHasErrors('levels');
        $this->put(route('settings.school.update'), array_replace($base, ['institution_code' => 'bad code!']))->assertSessionHasErrors('institution_code');

        $this->put(route('settings.school.update'), $base)->assertRedirect(route('settings.school'));
        $this->assertSame([9 => 'Basic 4', 10 => 'Basic 5', 11 => 'Basic 6'], FormLabel::options());
        $this->assertSame('FEN', Setting::get('institution_code'));
        $this->assertSame(40.0, \App\Services\TerminalReportService::classScoreMax());
    }

    public function test_the_institution_profile_name_wins_over_a_seeded_placeholder(): void
    {
        // Two rows for one key: a placeholder under "system" created first, the real name under "institution".
        DB::table('settings')->where('key', 'institution_name')->delete();
        DB::table('settings')->insert([['key' => 'institution_name', 'category' => 'system', 'value' => '[Your School Name]', 'created_at' => now(), 'updated_at' => now()]]);

        $this->put(route('settings.institution.update'), ['institution_name' => 'Fening School', 'institution_address' => 'Kumasi', 'institution_phone' => '0244000000', 'institution_email' => 'info@example.test'])->assertRedirect();
        $this->assertSame('Fening School', Setting::get('institution_name'));

        // Saving System Settings keeps the same single copy of the name, so the two screens agree.
        $this->put(route('settings.system.update'), ['institution_name' => 'Fening Basic School', 'institution_code' => 'FEN'])->assertRedirect();
        $this->assertSame('Fening Basic School', Setting::get('institution_name'));
        $this->assertSame(1, DB::table('settings')->where('key', 'institution_name')->where('category', 'institution')->count());
        $this->get(route('settings.system'))->assertOk()->assertSee('Fening Basic School');
    }

    public function test_institution_profile_stores_the_logo_where_the_site_reads_it(): void
    {
        \Storage::fake('public');
        $this->get(route('settings.institution'))->assertOk();

        $this->put(route('settings.institution.update'), [
            'institution_name' => 'Fening School', 'institution_address' => 'Kumasi', 'institution_phone' => '0244000000', 'institution_email' => 'info@example.test',
            'institution_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect();

        $path = Setting::get('institution_logo');
        $this->assertNotNull($path);
        \Storage::disk('public')->assertExists($path);

        $this->put(route('settings.institution.update'), ['institution_name' => 'X', 'institution_address' => 'Y', 'institution_phone' => '1', 'institution_email' => 'bad'])->assertSessionHasErrors('institution_email');
    }

    // ----------------------------------------------------------------- backup

    public function test_backup_page_and_file_guards(): void
    {
        $this->get(route('settings.backup'))->assertOk();

        $this->get(route('settings.backup.download', 'missing.sql'))->assertRedirect(route('settings.backup'));
        $this->get(route('settings.backup.download', 'notes.txt'))->assertRedirect(route('settings.backup'));
        $this->delete(route('settings.backup.destroy', 'missing.sql'))->assertSessionHas('error');
    }

    public function test_a_backup_can_be_made_listed_downloaded_and_deleted(): void
    {
        $dump = env('MYSQLDUMP_PATH', 'mysqldump');
        if (!is_file($dump) && trim((string) @shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'which ') . escapeshellarg($dump) . ' 2>&1')) === '') {
            $this->markTestSkipped('mysqldump is not available here.');
        }

        $this->post(route('settings.backup.create'))->assertRedirect(route('settings.backup'))->assertSessionHas('success');

        $file = collect(glob(storage_path('app/backups/backup_*.sql')))->sortDesc()->first();
        $this->assertNotNull($file);
        $name = basename($file);

        $this->get(route('settings.backup'))->assertOk()->assertSee($name);
        $this->get(route('settings.backup.download', $name))->assertOk();
        $this->delete(route('settings.backup.destroy', $name))->assertSessionHas('success');
        $this->assertFileDoesNotExist($file);
    }

    public function test_settings_are_for_admins_only(): void
    {
        $this->actingAs($this->staff('Accountant'));
        foreach ([route('settings.index'), route('settings.school'), route('settings.backup'), route('semesters.index')] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}

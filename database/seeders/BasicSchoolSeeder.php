<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\Grade;
use App\Models\GradeScheme;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Subject;
use App\Support\FormLabel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sets up a Ghanaian private basic school (KG, Primary, JHS): three terms per academic year,
 * GES/NaCCA grading for primary and BECE-style 1-9 grading for JHS, the usual fee items, the
 * GES subjects for each class level, and the report-card settings.
 *
 * Safe to re-run: everything is created with firstOrCreate/updateOrCreate.
 */
class BasicSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGradeSchemes();
        $this->seedTerms();
        $this->seedFeeCategories();
        $this->seedSubjects();
        $this->seedSettings();
        $this->seedSmsTemplates();
    }

    private function seedSmsTemplates(): void
    {
        $templates = [
            'Fees reminder' => 'Dear {parent}, this is a reminder that {pupil} ({class}) has an outstanding fees balance of {balance}. Kindly pay at the school office. Thank you. - {school}',
            'PTA meeting' => 'Dear {parent}, you are invited to the PTA meeting on [day, date] at [time] at the school. Your presence is important. - {school}',
            'Vacation notice' => 'Dear {parent}, school vacates on [date]. Terminal reports will be given out on that day. Next term begins on [date]. - {school}',
            'Reopening reminder' => 'Dear {parent}, school reopens on [date]. Please ensure {first_name} reports early with all items needed. - {school}',
            'Exams timetable' => 'Dear {parent}, end-of-term examinations start on [date]. Please help {first_name} prepare well. - {school}',
            'Holiday / closure' => 'Dear {parent}, please note that school will be closed on [date] for [reason]. Classes resume on [date]. - {school}',
            'Staff meeting' => 'Dear {name}, there will be a staff meeting on [day] at [time] in the staff common room. - {school}',
        ];

        foreach ($templates as $name => $message) {
            \App\Models\SmsTemplate::firstOrCreate(['name' => $name], ['message' => $message]);
        }
    }

    private function seedGradeSchemes(): void
    {
        $schemes = [
            [
                'name' => 'JHS Grading (BECE 1-9)',
                'description' => 'BECE-style stanine grades used for JHS 1-3. Grade 1 is the highest; the aggregate is the sum of the 4 core subjects and the best 2 others.',
                'section' => FormLabel::SECTION_JHS,
                'is_default' => false,
                'grades' => [
                    ['1', 80, 1, 'Highest'],
                    ['2', 70, 2, 'Higher'],
                    ['3', 60, 3, 'High'],
                    ['4', 55, 4, 'High Average'],
                    ['5', 50, 5, 'Average'],
                    ['6', 45, 6, 'Low Average'],
                    ['7', 40, 7, 'Low'],
                    ['8', 35, 8, 'Lower'],
                    ['9', 0, 9, 'Lowest'],
                ],
            ],
            [
                'name' => 'Primary Grading (NaCCA)',
                'description' => 'Standards-based curriculum proficiency levels used for KG and Basic 1-6.',
                'section' => FormLabel::SECTION_PRIMARY,
                'is_default' => true,
                'grades' => [
                    ['A', 80, 1, 'Advanced'],
                    ['P', 68, 2, 'Proficient'],
                    ['AP', 54, 3, 'Approaching Proficiency'],
                    ['D', 40, 4, 'Developing'],
                    ['B', 0, 5, 'Beginning'],
                ],
            ],
        ];

        // The SHS WASSCE scheme doesn't apply to a basic school.
        GradeScheme::where('name', 'WASSCE Grading')->get()->each(function (GradeScheme $scheme) {
            $scheme->grades()->delete();
            $scheme->delete();
        });

        GradeScheme::query()->update(['is_default' => false]);

        foreach ($schemes as $data) {
            $scheme = GradeScheme::updateOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description'], 'section' => $data['section'], 'is_default' => $data['is_default']]
            );

            foreach ($data['grades'] as [$grade, $min, $value, $remark]) {
                Grade::updateOrCreate(
                    ['grade_scheme_id' => $scheme->id, 'grade' => $grade],
                    ['min_score' => $min, 'grade_value' => $value, 'remark' => $remark]
                );
            }
        }
    }

    /**
     * Rename SHS semesters to terms and make sure every academic year has three terms.
     */
    private function seedTerms(): void
    {
        $names = [1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'];

        foreach (AcademicYear::with('semesters')->get() as $year) {
            foreach ($year->semesters as $term) {
                if (isset($names[$term->semester_number])) {
                    $term->update(['name' => $names[$term->semester_number]]);
                }
            }

            if ($year->semesters->isEmpty()) {
                continue;
            }

            foreach ($names as $number => $name) {
                if (!$year->semesters->contains('semester_number', $number)) {
                    Semester::create([
                        'academic_year_id' => $year->id,
                        'name' => $name,
                        'semester_number' => $number,
                        'start_date' => $year->start_date,
                        'end_date' => $year->end_date,
                        'is_current' => false,
                    ]);
                }
            }
        }
    }

    private function seedFeeCategories(): void
    {
        $categories = [
            'tuition' => 'Tuition / School Fees',
            'feeding' => 'Feeding Fee',
            'transport' => 'Transport / Bus Fee',
            'pta-levy' => 'PTA Levy',
            'books' => 'Books & Stationery',
            'uniform' => 'Uniform',
            'ict' => 'ICT / Computer Fee',
            'exams' => 'Examination Fee',
            'extra-classes' => 'Extra Classes',
            'sports-culture' => 'Sports & Culture',
            'admission' => 'Admission Fee',
        ];

        foreach ($categories as $slug => $name) {
            FeeCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_protected' => $slug === 'tuition']);
        }

        // SHS-only categories nobody has used yet.
        FeeCategory::whereIn('slug', ['graduation', 'resit'])->get()
            ->reject(fn (FeeCategory $category) => $category->isInUse())
            ->each->delete();
    }

    /**
     * GES subjects for each section. [code prefix, title, is_core, section => [levels]]
     */
    private function seedSubjects(): void
    {
        $kg = [4, 5];
        $lowerPrimary = [6, 7, 8];
        $upperPrimary = [9, 10, 11];
        $jhs = [12, 13, 14];

        $subjects = [
            ['LIT', 'Language and Literacy', true, $kg],
            ['NUM', 'Numeracy', true, $kg],
            ['OWOP', 'Our World Our People', false, array_merge($kg, $lowerPrimary, $upperPrimary)],
            ['CA', 'Creative Arts', false, array_merge($kg, $lowerPrimary, $upperPrimary)],
            ['ENG', 'English Language', true, array_merge($lowerPrimary, $upperPrimary, $jhs)],
            ['MATH', 'Mathematics', true, array_merge($lowerPrimary, $upperPrimary, $jhs)],
            ['SCI', 'Science', true, array_merge($lowerPrimary, $upperPrimary, $jhs)],
            ['GHL', 'Ghanaian Language', false, array_merge($lowerPrimary, $upperPrimary, $jhs)],
            ['RME', 'Religious and Moral Education', false, array_merge($lowerPrimary, $upperPrimary, $jhs)],
            ['PE', 'Physical Education', false, array_merge($lowerPrimary, $upperPrimary)],
            ['HIST', 'History', false, $upperPrimary],
            ['FRE', 'French', false, array_merge($upperPrimary, $jhs)],
            ['COMP', 'Computing', false, array_merge($upperPrimary, $jhs)],
            ['SOC', 'Social Studies', true, $jhs],
            ['CT', 'Career Technology', false, $jhs],
            ['CAD', 'Creative Arts and Design', false, $jhs],
            ['PHE', 'Physical and Health Education', false, $jhs],
        ];

        foreach ($subjects as [$prefix, $title, $isCore, $levels]) {
            foreach ($levels as $level) {
                Subject::firstOrCreate(
                    ['code' => Subject::buildCode($prefix, $level)],
                    ['title' => $title, 'level' => $level, 'is_core' => $isCore, 'credit_hours' => 1]
                );
            }
        }

        // Remove leftover SHS demo subjects (old numeric codes like PHY101) that hold no scores.
        Subject::where('code', 'not like', '%-%')
            ->whereDoesntHave('results')
            ->get()
            ->each(function (Subject $subject) {
                if (DB::table('continuous_assessments')->where('subject_id', $subject->id)->exists()) {
                    return;
                }
                DB::table('subject_programme')->where('subject_id', $subject->id)->delete();
                DB::table('subject_teacher')->where('subject_id', $subject->id)->delete();
                DB::table('timetable_entries')->where('subject_id', $subject->id)->delete();
                $subject->delete();
            });
    }

    private function seedSettings(): void
    {
        $settings = [
            ['class_score_max', 'academic', '50', 'Maximum class score (continuous assessment) on the terminal report'],
            ['exam_score_max', 'academic', '50', 'Maximum end-of-term exam score on the terminal report'],
            ['levels_offered', 'academic', implode(',', FormLabel::DEFAULT_OFFERED), 'Class levels this school runs'],
            ['head_title', 'institution', 'Head Teacher', 'Title printed under the head\'s signature on reports'],
            ['report_show_fees', 'academic', '1', 'Show the fee balance on terminal reports'],
        ];

        foreach ($settings as [$key, $category, $value, $description]) {
            Setting::firstOrCreate(['key' => $key], ['category' => $category, 'value' => $value, 'description' => $description]);
        }

        Setting::where('key', 'institution_code')->where('value', 'SHS')->update(['value' => 'SCH']);
        Setting::where('key', 'transcript_signature')->where('value', 'Headmaster')->update(['value' => 'Head Teacher']);
    }
}

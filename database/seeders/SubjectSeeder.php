<?php

namespace Database\Seeders;

use App\Models\Programme;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Every SHS subject runs continuously across Form 1, 2 and 3 (not just
     * Form 1), so each subject definition below is seeded as three rows -
     * one per level/form - sharing the same programme attachments. Codes
     * follow the existing "LETTERS + form-digit + 01" convention (e.g.
     * ENG101 for Form 1, ENG201 for Form 2, ENG301 for Form 3).
     */
    protected const FORMS = [100, 200, 300];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed against a real semester (created by SemesterSeeder, which must run
        // before this seeder) rather than a hardcoded id that may not exist.
        $semesterId = Semester::first()?->id;

        $programmeCodes = [
            'GEN-SCI', 'GEN-ARTS', 'BUSINESS', 'VIS-ART', 'HOME-ECON', 'AGRIC',
        ];
        $programmeIds = Programme::whereIn('code', $programmeCodes)
            ->pluck('id', 'code');

        // Core subjects: attached to every programme, is_core = true.
        $coreSubjects = [
            ['code' => 'ENG101', 'title' => 'English Language'],
            ['code' => 'MATH101', 'title' => 'Core Mathematics'],
            ['code' => 'SCI101', 'title' => 'Integrated Science'],
            ['code' => 'SOC101', 'title' => 'Social Studies'],
        ];

        foreach ($coreSubjects as $subjectData) {
            $this->createAcrossForms($subjectData, true, $semesterId, $programmeIds->all());
        }

        // Elective subjects: 4 per programme, matching the track's description.
        // Subjects that legitimately repeat across programmes (e.g. Chemistry,
        // Elective Mathematics, General Knowledge in Art) reuse the same rows
        // via firstOrCreate and are simply attached to both programmes.
        $electivesByProgramme = [
            'GEN-SCI' => [
                ['code' => 'PHY101', 'title' => 'Physics'],
                ['code' => 'CHEM101', 'title' => 'Chemistry'],
                ['code' => 'BIO101', 'title' => 'Biology'],
                ['code' => 'EMATH101', 'title' => 'Elective Mathematics'],
            ],
            'GEN-ARTS' => [
                ['code' => 'LIT101', 'title' => 'Literature in English'],
                ['code' => 'GOV101', 'title' => 'Government'],
                ['code' => 'HIST101', 'title' => 'History'],
                ['code' => 'CRS101', 'title' => 'Christian Religious Studies'],
            ],
            'BUSINESS' => [
                ['code' => 'FACC101', 'title' => 'Financial Accounting'],
                ['code' => 'CACC101', 'title' => 'Cost Accounting'],
                ['code' => 'BMGT101', 'title' => 'Business Management'],
                ['code' => 'ECON101', 'title' => 'Economics'],
            ],
            'VIS-ART' => [
                ['code' => 'GRAP101', 'title' => 'Graphic Design'],
                ['code' => 'PICT101', 'title' => 'Picture Making'],
                ['code' => 'CERA101', 'title' => 'Ceramics'],
                ['code' => 'GKA101', 'title' => 'General Knowledge in Art'],
            ],
            'HOME-ECON' => [
                ['code' => 'FNUT101', 'title' => 'Food and Nutrition'],
                ['code' => 'MLIV101', 'title' => 'Management in Living'],
                ['code' => 'CTEX101', 'title' => 'Clothing and Textiles'],
                ['code' => 'GKA101', 'title' => 'General Knowledge in Art'], // shared with VIS-ART
            ],
            'AGRIC' => [
                ['code' => 'AGRI101', 'title' => 'General Agriculture'],
                ['code' => 'ANHU101', 'title' => 'Animal Husbandry'],
                ['code' => 'CHEM101', 'title' => 'Chemistry'], // shared with GEN-SCI
                ['code' => 'EMATH101', 'title' => 'Elective Mathematics'], // shared with GEN-SCI
            ],
        ];

        foreach ($electivesByProgramme as $programmeCode => $subjects) {
            $programmeId = $programmeIds[$programmeCode] ?? null;

            if (!$programmeId) {
                continue;
            }

            foreach ($subjects as $subjectData) {
                $this->createAcrossForms($subjectData, false, $semesterId, [$programmeId]);
            }
        }
    }

    /**
     * Create (or reuse, for shared electives) one subject row per form, all
     * attached to the given programmes.
     */
    protected function createAcrossForms(array $subjectData, bool $isCore, ?int $semesterId, array $programmeIds): void
    {
        foreach (self::FORMS as $level) {
            $code = $this->codeForForm($subjectData['code'], $level);

            $subject = Subject::firstOrCreate(
                ['code' => $code],
                [
                    'title' => $subjectData['title'],
                    'description' => '',
                    'credit_hours' => 3,
                    'level' => $level,
                    'semester_id' => $semesterId,
                    'is_core' => $isCore,
                ]
            );

            $subject->programmes()->syncWithoutDetaching($programmeIds);
        }
    }

    /**
     * Derive the per-form subject code from a Form 1 base code, e.g.
     * "ENG101" + 200 -> "ENG201", "ENG101" + 300 -> "ENG301".
     */
    protected function codeForForm(string $baseCode, int $level): string
    {
        $formDigit = (int) ($level / 100);

        return preg_replace('/101$/', $formDigit . '01', $baseCode);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\GradeScheme;
use Illuminate\Database\Seeder;

class GradeSchemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the default WASSCE grading scheme.
        $gradeScheme = GradeScheme::firstOrCreate(
            ['name' => 'WASSCE Grading'],
            [
                'description' => 'WASSCE grade bands (A1-F9) used for SHS results',
                'is_default' => true,
            ]
        );

        // WASSCE grade bands. grade_value is on a 1 (best) to 9 (worst) scale,
        // matching Student::calculateAggregate()'s best-6-subjects aggregate logic.
        $grades = [
            ['grade' => 'A1', 'min_score' => 80.0, 'grade_value' => 1],
            ['grade' => 'B2', 'min_score' => 75.0, 'grade_value' => 2],
            ['grade' => 'B3', 'min_score' => 70.0, 'grade_value' => 3],
            ['grade' => 'C4', 'min_score' => 65.0, 'grade_value' => 4],
            ['grade' => 'C5', 'min_score' => 60.0, 'grade_value' => 5],
            ['grade' => 'C6', 'min_score' => 55.0, 'grade_value' => 6],
            ['grade' => 'D7', 'min_score' => 50.0, 'grade_value' => 7],
            ['grade' => 'E8', 'min_score' => 45.0, 'grade_value' => 8],
            ['grade' => 'F9', 'min_score' => 0.0, 'grade_value' => 9],
        ];

        // Add grades to the scheme
        foreach ($grades as $grade) {
            Grade::firstOrCreate(
                [
                    'grade_scheme_id' => $gradeScheme->id,
                    'grade' => $grade['grade'],
                ],
                [
                    'min_score' => $grade['min_score'],
                    'grade_value' => $grade['grade_value'],
                ]
            );
        }
    }
}

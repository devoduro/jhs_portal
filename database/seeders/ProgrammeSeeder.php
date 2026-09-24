<?php

namespace Database\Seeders;

use App\Models\Programme;
use Illuminate\Database\Seeder;

class ProgrammeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Ghanaian Senior High School (SHS) programmes/tracks.
        // All tracks run 3 years (SHS 1-3), so terminalLevel() = 100 + (3-1)*100 = 300.
        $programmes = [
            [
                'name' => 'General Science',
                'code' => 'GEN-SCI',
                'duration_years' => 3,
                'description' => 'Core subjects plus Physics, Chemistry, Biology, Elective Mathematics',
                'department' => 'General Science',
                'faculty' => 'Science',
            ],
            [
                'name' => 'General Arts',
                'code' => 'GEN-ARTS',
                'duration_years' => 3,
                'description' => 'Core subjects plus Literature in English, Government, History, Christian Religious Studies',
                'department' => 'General Arts',
                'faculty' => 'Arts',
            ],
            [
                'name' => 'Business',
                'code' => 'BUSINESS',
                'duration_years' => 3,
                'description' => 'Core subjects plus Financial Accounting, Cost Accounting, Business Management, Economics',
                'department' => 'Business',
                'faculty' => 'Business',
            ],
            [
                'name' => 'Visual Arts',
                'code' => 'VIS-ART',
                'duration_years' => 3,
                'description' => 'Core subjects plus Graphic Design, Picture Making, Ceramics, General Knowledge in Art',
                'department' => 'Visual Arts',
                'faculty' => 'Arts',
            ],
            [
                'name' => 'Home Economics',
                'code' => 'HOME-ECON',
                'duration_years' => 3,
                'description' => 'Core subjects plus Food and Nutrition, Management in Living, Clothing and Textiles, General Knowledge in Art',
                'department' => 'Home Economics',
                'faculty' => 'Arts',
            ],
            [
                'name' => 'Agricultural Science',
                'code' => 'AGRIC',
                'duration_years' => 3,
                'description' => 'Core subjects plus General Agriculture, Animal Husbandry, Chemistry, Elective Mathematics',
                'department' => 'Agricultural Science',
                'faculty' => 'Science',
            ],
        ];

        foreach ($programmes as $programme) {
            Programme::firstOrCreate(
                ['code' => $programme['code']],
                $programme
            );
        }
    }
}

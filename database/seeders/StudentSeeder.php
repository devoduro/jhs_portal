<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create students with Ghanaian names and context
        $students = [
            [
                'index_number' => '525SHS001',
                'full_name' => 'Kwesi Mensah',
                'date_of_birth' => '2008-05-15',
                'gender' => 'Male',
                'programme_id' => 1, // General Science
                'level' => 100, // SHS 1
                'status' => 'active',
                'house_id' => House::where('name', 'Aggrey House')->first()?->id,
                'boarding_status' => 'boarder',
                'email' => 'kmensah@student.example.com',
                'phone' => '+233 24 123 4567',
                'emergency_contact_name' => 'Abena Mensah',
                'emergency_contact_phone' => '+233 24 987 6543',
                'emergency_contact_relationship' => 'Mother',
            ],
        ];

        foreach ($students as $student) {
            Student::firstOrCreate(
                ['index_number' => $student['index_number']],
                $student
            );
        }
    }
}

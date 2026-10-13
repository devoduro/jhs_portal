<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TermReport;
use App\Models\User;
use App\Services\FeeLedgerService;
use App\Services\TerminalReportService;
use Illuminate\Database\Seeder;

/**
 * Fills a database with a believable demo school so the system can be explored: classes
 * from KG 1 to JHS 3 with class teachers, pupils, this term's fees billed and partly paid,
 * scores for every subject and class-teacher remarks.
 *
 * DEMO ONLY - never run on the live school database:
 *   php artisan db:seed --class=DemoSchoolSeeder
 */
class DemoSchoolSeeder extends Seeder
{
    private const FIRST = ['Kofi', 'Ama', 'Kwame', 'Akua', 'Yaw', 'Abena', 'Kwabena', 'Esi', 'Kojo', 'Efua', 'Kwesi', 'Adwoa', 'Nii', 'Naa', 'Fiifi', 'Afia', 'Selorm', 'Dzifa', 'Kweku', 'Akosua'];
    private const LAST = ['Mensah', 'Asante', 'Owusu', 'Boateng', 'Osei', 'Agyeman', 'Darko', 'Quaye', 'Tetteh', 'Frimpong', 'Adjei', 'Ansah', 'Appiah', 'Amoah', 'Sarpong', 'Addo', 'Nkrumah', 'Badu'];

    public function run(): void
    {
        mt_srand(2026);
        $this->call([RolesAndPermissionsSeeder::class]);

        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['start_date' => '2026-09-08', 'end_date' => '2027-07-30']);
        AcademicYear::query()->update(['is_current' => false]);
        AcademicYear::whereKey($year->id)->update(['is_current' => true]);   // (not $year->update: the model still thinks it is current, so nothing would be saved)

        $dates = [1 => ['2026-09-08', '2026-12-18', '2027-01-12'], 2 => ['2027-01-12', '2027-04-09', '2027-05-04'], 3 => ['2027-05-04', '2027-07-30', '2027-09-07']];
        foreach ([1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'] as $n => $name) {
            Semester::updateOrCreate(['academic_year_id' => $year->id, 'semester_number' => $n], [
                'name' => $name, 'start_date' => $dates[$n][0], 'end_date' => $dates[$n][1],
                'vacation_date' => $dates[$n][1], 'next_term_begins' => $dates[$n][2], 'attendance_days' => 68,
            ]);
        }
        Semester::query()->update(['is_current' => false]);
        $term = Semester::where('academic_year_id', $year->id)->where('semester_number', 1)->first();
        $term->update(['is_current' => true]);

        $this->call([BasicSchoolSeeder::class]);

        $classes = ['KG 1' => 4, 'KG 2' => 5, 'Basic 1' => 6, 'Basic 2' => 7, 'Basic 3' => 8, 'Basic 4' => 9, 'Basic 5' => 10, 'Basic 6' => 11, 'JHS 1' => 12, 'JHS 2' => 13, 'JHS 3' => 14];
        $teacherNames = ['Mrs. Grace Owusu', 'Mr. Daniel Asare', 'Ms. Linda Boakye', 'Mr. Emmanuel Tetteh', 'Mrs. Joyce Amponsah', 'Mr. Isaac Mensah',
            'Ms. Patience Adu', 'Mr. Samuel Ofori', 'Mrs. Mercy Danquah', 'Mr. Richard Kumi', 'Mr. Francis Ankrah'];

        // Term fees per level.
        $fees = [
            'tuition' => [4 => 900, 5 => 900, 6 => 1100, 7 => 1100, 8 => 1100, 9 => 1250, 10 => 1250, 11 => 1250, 12 => 1500, 13 => 1500, 14 => 1650],
            'feeding' => array_fill_keys(range(4, 14), 520),
            'pta-levy' => array_fill_keys(range(4, 14), 50),
            'books' => [9 => 180, 10 => 180, 11 => 180, 12 => 250, 13 => 250, 14 => 250],
            'exams' => [14 => 200],
        ];
        foreach ($fees as $category => $byLevel) {
            foreach ($byLevel as $level => $amount) {
                FeeStructure::updateOrCreate(['semester_id' => $term->id, 'category' => $category, 'level' => $level], ['academic_year_id' => $year->id, 'amount' => $amount]);
            }
        }

        $n = Student::count();
        foreach (array_values($classes) as $i => $level) {
            $name = array_keys($classes)[$i] . 'A';
            $teacher = Teacher::firstOrCreate(['email' => 'teacher' . ($i + 1) . '@demo.school'], ['name' => $teacherNames[$i], 'staff_id' => 'TCH' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)]);
            $class = ClassGroup::firstOrCreate(['name' => $name], ['level' => $level, 'class_teacher_id' => $teacher->id]);

            for ($p = 0; $p < 9; $p++) {
                $n++;
                $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
                $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
                Student::firstOrCreate(['index_number' => 'DEMO/2026/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT)], [
                    'full_name' => strtoupper($last) . ' ' . $first,
                    'gender' => in_array($first, ['Ama', 'Akua', 'Abena', 'Esi', 'Efua', 'Adwoa', 'Naa', 'Afia', 'Dzifa', 'Akosua']) ? 'Female' : 'Male',
                    'date_of_birth' => now()->subYears($level - 1)->subDays(mt_rand(0, 360)),
                    'class_group_id' => $class->id,
                    'level' => $level,
                    'status' => 'active',
                    'emergency_contact_name' => 'Mr./Mrs. ' . ucfirst(strtolower($last)),
                    'emergency_contact_relationship' => mt_rand(0, 1) ? 'Father' : 'Mother',
                    'emergency_contact_phone' => '02' . mt_rand(0, 9) . mt_rand(1000000, 9999999),
                ]);
            }
        }

        $students = Student::where('status', 'active')->whereNotNull('class_group_id')->get();
        FeeLedgerService::billTerm($term, $students);

        // Payments over the last fortnight: most pay part, some pay in full, a few nothing yet.
        foreach ($students as $student) {
            $bill = $student->billedAmount(null, $term);
            $share = [0, 0.3, 0.5, 0.5, 0.7, 1, 1, 1][mt_rand(0, 7)];
            if ($share > 0 && !StudentPayment::where('student_id', $student->id)->exists()) {
                StudentPayment::create([
                    'student_id' => $student->id, 'academic_year_id' => $year->id, 'semester_id' => $term->id,
                    'amount' => round($bill * $share, -1), 'payment_method' => ['cash', 'mobile_money', 'mobile_money', 'bank_transfer'][mt_rand(0, 3)],
                    'payment_date' => now()->subDays(mt_rand(0, 13)), 'paid_by' => $student->emergency_contact_name,
                ]);
            }
        }

        // Scores for every class (the top classes are fully done, the rest partly - like mid-term).
        foreach (ClassGroup::all() as $class) {
            $roll = Student::where('class_group_id', $class->id)->get();
            $subjects = Subject::where('level', $class->level)->get();
            $done = $class->level >= 9 ? $subjects : $subjects->take((int) ceil($subjects->count() / 2));
            foreach ($done as $subject) {
                $scores = [];
                foreach ($roll as $pupil) {
                    $ability = (crc32($pupil->full_name) % 30) + 15;
                    $scores[$pupil->id] = ['class_score' => min(50, $ability + mt_rand(0, 12)), 'exam_score' => min(50, $ability + mt_rand(-5, 15))];
                }
                TerminalReportService::saveScores($class, $subject, $term, $scores);
            }

            if ($class->level >= 12) {
                foreach ($roll as $pupil) {
                    TermReport::updateOrCreate(['student_id' => $pupil->id, 'semester_id' => $term->id], [
                        'class_group_id' => $class->id, 'days_present' => mt_rand(58, 68), 'conduct' => 'Very Good',
                        'attitude' => 'Hardworking', 'interest' => 'Reading',
                        'class_teacher_remark' => 'A good performance. Keep working hard.',
                    ]);
                }
            }
        }

        // A teacher login for trying the teacher view (password: password).
        $teacher = Teacher::where('email', 'teacher6@demo.school')->first();
        $user = User::firstOrCreate(['email' => 'teacher@demo.school'], [
            'name' => $teacher->name, 'password' => bcrypt('password'), 'role' => User::ROLE_ADMIN, 'teacher_id' => $teacher->id, 'first_login' => false,
        ]);
        $user->syncRoles(['Teacher']);
    }
}

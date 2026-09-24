<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts the SHS schema to a Ghanaian basic school (KG / Primary / JHS):
 *  - programmes become optional (classes carry the level instead),
 *  - fees are billed per term (fee structures, charges and payments carry a term),
 *  - results split into class score + exam score, snapshot the class they were earned in,
 *  - terms carry vacation / next-term dates and attendance days for the terminal report,
 *  - a term_reports table holds each pupil's attendance, conduct and remarks per term,
 *  - grade bands carry a remark and schemes apply to a school section.
 *
 * Level values move from SHS 100/200/300 to the basic-school scale in App\Support\FormLabel
 * (existing Form 1/2/3 rows are mapped to JHS 1/2/3).
 */
return new class extends Migration
{
    private const LEVEL_MAP = [100 => 12, 200 => 13, 300 => 14];

    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('programme_id')->nullable()->change();
        });

        Schema::table('class_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('programme_id')->nullable()->change();
            $table->foreignId('class_teacher_id')->nullable()->after('name')->constrained('teachers')->nullOnDelete();
        });

        Schema::table('class_groups', function (Blueprint $table) {
            // The programme FK was leaning on this unique index - give it its own first.
            $table->index('programme_id');
            $table->dropUnique('class_groups_programme_id_level_name_unique');
            $table->unique('name');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->unsignedBigInteger('programme_id')->nullable()->change();
            $table->foreignId('semester_id')->nullable()->after('academic_year_id')->constrained('semesters')->cascadeOnDelete();
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('academic_year_id')->constrained('semesters')->nullOnDelete();
            $table->unique(['student_id', 'semester_id', 'category'], 'student_fee_charges_student_term_category_unique');
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->dropUnique('student_fee_charges_student_id_academic_year_id_category_unique');
        });

        Schema::table('student_payments', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('academic_year_id')->constrained('semesters')->nullOnDelete();
            $table->string('receipt_number')->nullable()->unique()->after('id');
            $table->string('paid_by')->nullable()->after('notes');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->decimal('class_score', 5, 2)->nullable()->after('academic_year_id');
            $table->decimal('exam_score', 5, 2)->nullable()->after('class_score');
            $table->foreignId('class_group_id')->nullable()->after('student_id')->constrained('class_groups')->nullOnDelete();
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->date('vacation_date')->nullable()->after('end_date');
            $table->date('next_term_begins')->nullable()->after('vacation_date');
            $table->unsignedSmallInteger('attendance_days')->nullable()->after('next_term_begins');
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->string('remark')->nullable()->after('grade_value');
        });

        Schema::table('grade_schemes', function (Blueprint $table) {
            $table->string('section')->nullable()->after('description');
        });

        Schema::create('term_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('class_group_id')->nullable()->constrained('class_groups')->nullOnDelete();
            $table->unsignedSmallInteger('days_present')->nullable();
            $table->string('conduct')->nullable();
            $table->string('attitude')->nullable();
            $table->string('interest')->nullable();
            $table->text('class_teacher_remark')->nullable();
            $table->text('head_teacher_remark')->nullable();
            $table->unsignedSmallInteger('promoted_to')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
        });

        foreach (['students', 'class_groups', 'subjects', 'fee_structures', 'ca_score_settings'] as $tableName) {
            foreach (self::LEVEL_MAP as $old => $new) {
                DB::table($tableName)->where('level', $old)->update(['level' => $new]);
            }
        }

        // Existing results were entered as a single total - keep it as the exam score so the
        // total still adds up; teachers can split it when they next edit the score sheet.
        DB::table('results')->whereNull('exam_score')->update(['exam_score' => DB::raw('score')]);
    }

    public function down(): void
    {
        foreach (['students', 'class_groups', 'subjects', 'fee_structures', 'ca_score_settings'] as $tableName) {
            foreach (self::LEVEL_MAP as $old => $new) {
                DB::table($tableName)->where('level', $new)->update(['level' => $old]);
            }
        }

        Schema::dropIfExists('term_reports');

        Schema::table('grade_schemes', fn (Blueprint $table) => $table->dropColumn('section'));
        Schema::table('grades', fn (Blueprint $table) => $table->dropColumn('remark'));
        Schema::table('semesters', fn (Blueprint $table) => $table->dropColumn(['vacation_date', 'next_term_begins', 'attendance_days']));

        Schema::table('results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_group_id');
            $table->dropColumn(['class_score', 'exam_score']);
        });

        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
            $table->dropUnique(['receipt_number']);
            $table->dropColumn(['receipt_number', 'paid_by']);
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_year_id', 'category']);
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->dropUnique('student_fee_charges_student_term_category_unique');
            $table->dropConstrainedForeignId('semester_id');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });

        Schema::table('class_groups', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['programme_id', 'level', 'name']);
            $table->dropConstrainedForeignId('class_teacher_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'teacher_id']);
        });

        // Preserve every subject's existing single teacher assignment as the first pivot row.
        $now = now();
        $rows = DB::table('subjects')->whereNotNull('teacher_id')->get(['id', 'teacher_id']);

        foreach ($rows as $row) {
            DB::table('subject_teacher')->insert([
                'subject_id' => $row->id,
                'teacher_id' => $row->teacher_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('is_core')->constrained('teachers')->nullOnDelete();
        });

        // Restore the first pivot assignment per subject as the single teacher_id.
        $firstAssignments = DB::table('subject_teacher')
            ->select('subject_id', DB::raw('MIN(teacher_id) as teacher_id'))
            ->groupBy('subject_id')
            ->get();

        foreach ($firstAssignments as $assignment) {
            DB::table('subjects')->where('id', $assignment->subject_id)->update(['teacher_id' => $assignment->teacher_id]);
        }

        Schema::dropIfExists('subject_teacher');
    }
};

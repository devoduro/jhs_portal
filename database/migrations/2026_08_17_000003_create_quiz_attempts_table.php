<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->enum('status', ['in_progress', 'completed', 'expired'])->default('in_progress');
            // dateTime, not timestamp - MySQL strict mode rejects a second non-nullable TIMESTAMP
            // column with no explicit default in the same table ("Invalid default value").
            $table->dateTime('started_at');
            $table->dateTime('ends_at');
            $table->timestamp('submitted_at')->nullable();

            // Snapshot at grading time so later question edits never retroactively change a
            // student's already-graded result.
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->integer('correct_count')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_passed')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};

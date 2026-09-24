<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();

            // Subjects are already level-specific in this app (e.g. ENG201 = English Language,
            // Form 2), so the subject alone determines who the quiz is for - no separate level
            // column to risk drifting out of sync with it.
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            // Optional narrower targeting to one class within that subject's form; null means
            // every student taking the subject.
            $table->foreignId('class_group_id')->nullable()->constrained('class_groups')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->integer('duration_minutes');
            $table->decimal('pass_percentage', 5, 2)->default(50);
            $table->boolean('has_negative_marking')->default(false);
            $table->decimal('negative_mark_value', 8, 2)->nullable();
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('allow_retake')->default(false);

            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};

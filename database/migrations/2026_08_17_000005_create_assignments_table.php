<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->dateTime('due_at');
            $table->boolean('allow_late_submission')->default(false);
            $table->decimal('max_score', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();
        });

        // Which classes this assignment is set for - a teacher can pick several at once,
        // unlike a quiz's single optional class (see quizzes.class_group_id).
        Schema::create('assignment_class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('class_group_id')->constrained('class_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'class_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_class_groups');
        Schema::dropIfExists('assignments');
    }
};

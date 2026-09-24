<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();

            $table->text('question_text');
            $table->enum('type', ['mcq', 'true_false'])->default('mcq');
            // {"A": "option text", "B": "option text", ...} - for true_false, {"true": "True", "false": "False"}
            $table->json('options');
            // The option key (e.g. "A") that is correct.
            $table->string('correct_answer');
            $table->decimal('marks', 8, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};

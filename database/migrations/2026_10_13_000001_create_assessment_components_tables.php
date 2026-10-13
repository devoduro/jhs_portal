<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-defined score sheet columns. A class level with no components keeps the plain
     * "class score + exam score" sheet (split taken from School Settings); a level with
     * components is scored on exactly those columns - e.g. Exercises 10, Class Test 10,
     * Project 10, Homework 10, Exam 60 - and each component counts towards either the pupil's
     * class (continuous assessment) score or the exam score on the terminal report.
     */
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('level');
            $table->string('label');
            $table->string('type', 10)->default('class'); // class | exam
            $table->decimal('max_mark', 5, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['level', 'label']);
            $table->index(['level', 'position']);
        });

        Schema::create('result_component_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained('assessment_components')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['result_id', 'assessment_component_id'], 'result_component_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_component_scores');
        Schema::dropIfExists('assessment_components');
    }
};

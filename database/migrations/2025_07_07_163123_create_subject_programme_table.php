<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subject_programme', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('programme_id')->constrained()->onDelete('cascade');
            $table->primary(['subject_id', 'programme_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_programme');
    }
};

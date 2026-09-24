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
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->nullable()->after('credit_hours');
        });

        // Backfill from the subject code's numbering convention (e.g. CEBC233 -> level 200):
        // the digit immediately after the letter prefix is the level's leading digit.
        DB::table('subjects')->whereNull('level')->get(['id', 'code'])->each(function ($subject) {
            if (preg_match('/^[A-Za-z]+(\d)\d{2}[A-Za-z]*$/', $subject->code, $matches)) {
                DB::table('subjects')->where('id', $subject->id)->update(['level' => ((int) $matches[1]) * 100]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};

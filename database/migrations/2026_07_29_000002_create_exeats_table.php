<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An exeat is authorized leave for a boarding student to be off campus
     * for a date range. Granted by a house master (or admin) for their own
     * house's boarders; the parent is texted the reason on creation.
     */
    public function up(): void
    {
        Schema::create('exeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sms_log_id')->nullable()->constrained('sms_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeats');
    }
};

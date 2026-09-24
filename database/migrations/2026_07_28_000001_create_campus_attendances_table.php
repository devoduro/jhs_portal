<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records each campus gate in/out event, derived from a biometric device punch.
     * This is distinct from lesson_attendances (classroom attendance) and
     * biometric_registrations (one-off per-semester registration check-in).
     */
    public function up(): void
    {
        Schema::create('campus_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('biometric_log_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->dateTime('occurred_at');
            $table->foreignId('sms_log_id')->nullable()->constrained('sms_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_attendances');
    }
};

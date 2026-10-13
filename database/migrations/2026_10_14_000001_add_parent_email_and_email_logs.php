<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A parent/guardian email address per pupil (the existing `email` column is the pupil's own),
     * and a log of the emails the school sends to parents - results and fee statements - so a
     * failed or repeated send can be traced the way SMS already can.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('parent_email')->nullable()->after('emergency_contact_phone');
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('type', 20); // results | fees
            $table->string('email');
            $table->string('subject');
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('parent_email'));
    }
};

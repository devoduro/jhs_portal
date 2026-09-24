<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();

            // Placement data - normally bulk-imported from the GES CSSPS placement
            // export before an applicant ever visits the portal (see AdmissionImport).
            $table->string('index_number')->unique();
            $table->string('full_name');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->enum('boarding_status', ['day', 'boarder'])->nullable();

            // Set by the applicant once they log in to complete their profile.
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('guardian_name')->nullable();
            $table->text('address')->nullable();
            $table->string('town')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('digital_address')->nullable();
            $table->string('ghana_card_number')->nullable();
            $table->string('jhs_attended')->nullable();
            $table->enum('jhs_type', ['Public', 'Private'])->nullable();
            $table->string('aggregate_score', 10)->nullable();
            $table->string('nationality')->nullable();
            $table->string('religion')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('photo_path')->nullable();

            // Applicant portal login - deliberately not the app's users table (see
            // ApplicantAuthController): applicants are unverified/temporary until
            // admitted, and the users table only allows admin/student roles.
            $table->string('password')->nullable();

            // placed: imported, not yet paid. payment_pending: a Speso collection is
            // in flight. paid: fee confirmed, applicant can log in and complete their
            // profile. submitted: profile complete, awaiting admin review. admitted:
            // confirmed into the main system (student_id set). rejected: declined.
            $table->enum('status', ['placed', 'payment_pending', 'paid', 'submitted', 'admitted', 'rejected'])
                ->default('placed');

            $table->decimal('fee_amount', 8, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};

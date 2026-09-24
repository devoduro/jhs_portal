<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained()->cascadeOnDelete();

            // order_reference is ours (sent to Speso as order_id); speso_reference is
            // theirs, filled in once they acknowledge/complete the collection.
            $table->string('order_reference')->unique();
            $table->string('speso_reference')->nullable();
            $table->string('network', 10)->nullable();
            $table->string('mobile', 20);
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_payments');
    }
};

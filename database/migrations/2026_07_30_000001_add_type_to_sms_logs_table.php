<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes what triggered an SMS log row: a manual bulk 'campaign'
     * (has sms_campaign_id set already, but tagged too for consistent
     * filtering), an 'exeat' parent notification, or a 'campus_checkin'
     * parent notification.
     */
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('type')->nullable()->after('sms_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

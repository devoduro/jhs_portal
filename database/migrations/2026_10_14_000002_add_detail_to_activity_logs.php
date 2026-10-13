<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activity logs used to record only logins. Every change a signed-in user makes (a payment
     * received, scores saved, a setting changed...) is now logged too, so each entry also keeps
     * what was acted on and which screen it came from, and the log can be filtered by date and action.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('route_name')->nullable()->after('description');
            $table->string('method', 10)->nullable()->after('route_name');
            $table->string('subject')->nullable()->after('method');
            $table->index('created_at');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['action']);
            $table->dropColumn(['route_name', 'method', 'subject']);
        });
    }
};

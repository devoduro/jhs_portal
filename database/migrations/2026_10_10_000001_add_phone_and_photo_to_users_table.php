<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts get a mobile number (so a forgotten password can be reset by SMS) and a
 * profile photo (the profile page already offered an upload, but there was nowhere to keep it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email')->index();
            $table->string('profile_photo')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['phone', 'profile_photo']);
        });
    }
};

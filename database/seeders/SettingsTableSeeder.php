<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        // System settings
        $systemSettings = [
            [
                'key' => 'institution_name',
                'category' => 'system',
                'value' => '[Your School Name]',
                'description' => 'Name of the institution',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_code',
                'category' => 'system',
                'value' => 'SHS',
                'description' => 'Institution code',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_address',
                'category' => 'system',
                'value' => '[Your School Address]',
                'description' => 'Institution address',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_contact',
                'category' => 'system',
                'value' => '[Your School Phone Number]',
                'description' => 'Institution contact number',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'transcript_prefix',
                'category' => 'system',
                'value' => 'TR',
                'description' => 'Prefix for transcript numbers',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'transcript_footer',
                'category' => 'system',
                'value' => 'This transcript is not valid without the school seal and signature.',
                'description' => 'Text to appear at the bottom of transcripts',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'transcript_signature',
                'category' => 'system',
                'value' => 'Headmaster',
                'description' => 'Title of the signing authority',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'email_from_address',
                'category' => 'system',
                'value' => 'info@example.com',
                'description' => 'Email address used for sending system emails',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'email_from_name',
                'category' => 'system',
                'value' => '[Your School Name]',
                'description' => 'Name used for sending system emails',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        
        // Institution settings
        $institutionSettings = [
            [
                'key' => 'institution_name',
                'category' => 'institution',
                'value' => '[Your School Name]',
                'description' => 'Name of the institution',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_slogan',
                'category' => 'institution',
                'value' => 'Excellence in Education',
                'description' => 'Institution slogan or motto',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_address',
                'category' => 'institution',
                'value' => '[Your School Address]',
                'description' => 'Institution address',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_phone',
                'category' => 'institution',
                'value' => '[Your School Phone Number]',
                'description' => 'Institution phone number',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_email',
                'category' => 'institution',
                'value' => 'info@example.com',
                'description' => 'Institution email address',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'institution_website',
                'category' => 'institution',
                'value' => 'https://example.com',
                'description' => 'Institution website URL',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        
        // Insert all settings, leaving any already-customized values untouched.
        DB::table('settings')->insertOrIgnore(array_merge($systemSettings, $institutionSettings));
    }
}

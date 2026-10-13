<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The school's logo as printed documents should show it: the one uploaded under Settings >
 * Institution Profile, else the bundled default - so every printout matches the sidebar.
 */
class Branding
{
    public static function logoFile(): ?string
    {
        $uploaded = Setting::get('institution_logo');

        foreach ([$uploaded ? storage_path('app/public/' . $uploaded) : null, public_path('images/logos/institution_logo.png')] as $file) {
            if ($file && is_file($file) && filesize($file)) {
                return $file;
            }
        }

        return null;
    }

    /** The logo as a data: URI (works in print views and PDFs), or null when there is none. */
    public static function logoDataUri(): ?string
    {
        $file = self::logoFile();

        return $file
            ? 'data:' . (mime_content_type($file) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($file))
            : null;
    }
}

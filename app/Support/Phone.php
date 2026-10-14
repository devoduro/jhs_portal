<?php

namespace App\Support;

/**
 * Ghana mobile numbers the way the SMS gateway wants them: digits only with a leading 0
 * ("+233 24 123 4567", "233241234567" and "24 123 4567" all become "0241234567").
 */
class Phone
{
    public static function clean(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            return '0' . substr($digits, 3);
        }

        return strlen($digits) === 9 ? '0' . $digits : $digits;
    }
}

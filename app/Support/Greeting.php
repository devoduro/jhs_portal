<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * "Good morning, Mr. Mensah" / "Good afternoon, Ama" for dashboard heroes.
 */
class Greeting
{
    private const TITLES = ['mr', 'mrs', 'ms', 'miss', 'dr', 'rev', 'prof', 'madam', 'sir'];

    public static function salutation(?string $fullName): string
    {
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $name = self::shortName($fullName);

        return $greeting . ($name ? ', ' . $name : '');
    }

    public static function shortName(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim(Str::before((string) $fullName, '('))) ?: [];
        $words = array_values(array_filter($words));

        if (!$words) {
            return '';
        }

        if (in_array(strtolower(rtrim($words[0], '.')), self::TITLES, true) && count($words) > 1) {
            return $words[0] . ' ' . end($words);
        }

        return $words[0];
    }
}

<?php

namespace App\Support;

/**
 * Display-only mapping of the raw numeric `level` column (100/200/300 - unchanged
 * underlying storage/logic) to the "Form 1/2/3" labels used throughout the UI.
 * Options are capped at Form 1-3 since every seeded SHS programme runs 3 years
 * (Programme::terminalLevel() resolves to 300 for all of them).
 */
class FormLabel
{
    public const OPTIONS = [
        100 => 'Form 1',
        200 => 'Form 2',
        300 => 'Form 3',
    ];

    /**
     * Human-readable label for a raw level value, e.g. 100 -> "Form 1".
     * Falls back to "Level {n}" for any value outside the normal 100-300
     * range rather than hiding an unexpected value.
     */
    public static function label(?int $level): string
    {
        if ($level === null) {
            return 'Not set';
        }

        return self::OPTIONS[$level] ?? ('Level ' . $level);
    }
}

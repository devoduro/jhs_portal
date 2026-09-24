<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Class levels for a Ghanaian basic school (pre-school, primary and JHS), stored as the
 * small integer in the `level` column on students, class groups, subjects and fee
 * structures. The integers run in promotion order so "next level" is simply the next
 * key, and ordering by `level` in SQL lists classes in their natural order.
 *
 * Which levels a particular school actually runs is controlled by the `levels_offered`
 * setting (Settings > Institution), so a school without a creche/nursery doesn't see
 * those options in every dropdown.
 */
class FormLabel
{
    public const SECTION_PRESCHOOL = 'preschool';
    public const SECTION_PRIMARY = 'primary';
    public const SECTION_JHS = 'jhs';

    public const OPTIONS = [
        1 => 'Creche',
        2 => 'Nursery 1',
        3 => 'Nursery 2',
        4 => 'KG 1',
        5 => 'KG 2',
        6 => 'Basic 1',
        7 => 'Basic 2',
        8 => 'Basic 3',
        9 => 'Basic 4',
        10 => 'Basic 5',
        11 => 'Basic 6',
        12 => 'JHS 1',
        13 => 'JHS 2',
        14 => 'JHS 3',
    ];

    public const SECTIONS = [
        self::SECTION_PRESCHOOL => 'Pre-School (Creche - KG)',
        self::SECTION_PRIMARY => 'Primary (Basic 1 - 6)',
        self::SECTION_JHS => 'Junior High (JHS 1 - 3)',
    ];

    /** Levels offered when the school hasn't configured `levels_offered` yet: KG 1 to JHS 3. */
    public const DEFAULT_OFFERED = [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

    /**
     * The levels this school runs, in promotion order - what every level dropdown shows.
     */
    public static function options(): array
    {
        $offered = self::offeredLevels();

        return array_filter(self::OPTIONS, fn ($level) => in_array($level, $offered, true), ARRAY_FILTER_USE_KEY);
    }

    private static ?array $offeredCache = null;

    public static function offeredLevels(): array
    {
        if (self::$offeredCache !== null) {
            return self::$offeredCache;
        }

        $raw = null;

        try {
            $raw = Setting::get('levels_offered');
        } catch (\Throwable $e) {
            // Settings table unavailable (e.g. during migrations) - fall back to defaults.
        }

        $levels = array_values(array_filter(array_map('intval', explode(',', (string) $raw))));
        $levels = array_values(array_intersect($levels, array_keys(self::OPTIONS)));

        return self::$offeredCache = ($levels ?: self::DEFAULT_OFFERED);
    }

    public static function forgetOffered(): void
    {
        self::$offeredCache = null;
    }

    /**
     * Human-readable label for a raw level value, e.g. 12 -> "JHS 1".
     */
    public static function label(?int $level): string
    {
        if ($level === null) {
            return 'Not set';
        }

        return self::OPTIONS[$level] ?? ('Level ' . $level);
    }

    /**
     * Compact code for a level, used in subject codes: KG1, B4, J2...
     */
    public static function shortCode(int $level): string
    {
        return match (true) {
            $level === 1 => 'CR',
            $level <= 3 => 'N' . ($level - 1),
            $level <= 5 => 'KG' . ($level - 3),
            $level <= 11 => 'B' . ($level - 5),
            $level <= 14 => 'J' . ($level - 11),
            default => 'L' . $level,
        };
    }

    public static function section(?int $level): ?string
    {
        return match (true) {
            $level === null => null,
            $level <= 5 => self::SECTION_PRESCHOOL,
            $level <= 11 => self::SECTION_PRIMARY,
            default => self::SECTION_JHS,
        };
    }

    public static function isJhs(?int $level): bool
    {
        return self::section($level) === self::SECTION_JHS;
    }

    /**
     * The level a student moves up to after this one, or null when this is the school's
     * final level (students completing it leave the school).
     */
    public static function next(int $level): ?int
    {
        $offered = array_keys(self::options());
        $position = array_search($level, $offered, true);

        if ($position === false) {
            // Level no longer offered - step to the next offered level above it, if any.
            foreach ($offered as $candidate) {
                if ($candidate > $level) {
                    return $candidate;
                }
            }

            return null;
        }

        return $offered[$position + 1] ?? null;
    }

    public static function isFinal(int $level): bool
    {
        return self::next($level) === null;
    }

    /**
     * Validation rule fragment accepting any known level.
     */
    public static function rule(): string
    {
        return 'in:' . implode(',', array_keys(self::OPTIONS));
    }
}

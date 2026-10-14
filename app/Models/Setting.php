<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'category',
        'value',
        'description',
    ];

    /**
     * Look up a setting's value by key, falling back to $default when the key
     * isn't set (or the settings table hasn't been seeded yet).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        // A key can exist under more than one category (the school name is on both the Institution
        // Profile and System Settings screens). The Institution Profile is the one admins edit for
        // the school's identity, so it wins; otherwise a stale seeded placeholder could shadow it.
        return static::where('key', $key)
            ->orderByRaw("CASE category WHEN 'institution' THEN 0 WHEN 'academic' THEN 1 WHEN 'system' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->value('value') ?? $default;
    }
}

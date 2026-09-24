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
        return static::where('key', $key)->value('value') ?? $default;
    }
}

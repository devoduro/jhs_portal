<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'house_master_id',
    ];

    /**
     * Get the teacher assigned to manage this house, if any.
     */
    public function houseMaster(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'house_master_id');
    }

    /**
     * Get the students assigned to this house.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}

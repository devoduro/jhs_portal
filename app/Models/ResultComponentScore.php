<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultComponentScore extends Model
{
    use HasFactory;

    protected $fillable = ['result_id', 'assessment_component_id', 'score'];

    protected $casts = ['score' => 'decimal:2'];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }
}

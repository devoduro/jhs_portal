<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    public const TYPE_RESULTS = 'results';
    public const TYPE_FEES = 'fees';

    protected $fillable = ['student_id', 'semester_id', 'type', 'email', 'subject', 'success', 'error', 'sent_by'];

    protected $casts = ['success' => 'boolean'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}

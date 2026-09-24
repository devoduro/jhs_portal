<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'admission_application_id',
        'order_reference',
        'speso_reference',
        'network',
        'mobile',
        'amount',
        'status',
        'raw_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'completed_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function application()
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}

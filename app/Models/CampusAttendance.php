<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampusAttendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'biometric_device_id',
        'biometric_log_id',
        'direction',
        'occurred_at',
        'sms_log_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the student this gate event belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the device (gate) that recorded this event.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    /**
     * Get the raw biometric punch that generated this event.
     */
    public function biometricLog(): BelongsTo
    {
        return $this->belongsTo(BiometricLog::class);
    }

    /**
     * Get the parent-notification SMS log entry sent for this event, if any.
     */
    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(SmsLog::class);
    }
}

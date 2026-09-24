<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    public const TYPE_CAMPAIGN = 'campaign';
    public const TYPE_EXEAT = 'exeat';
    public const TYPE_CAMPUS_CHECKIN = 'campus_checkin';
    public const TYPE_GRADES = 'grades';
    public const TYPE_FEES = 'fees';

    public const TYPE_LABELS = [
        self::TYPE_CAMPAIGN => 'Bulk Campaign',
        self::TYPE_EXEAT => 'Exeat',
        self::TYPE_CAMPUS_CHECKIN => 'Campus Check-In',
        self::TYPE_GRADES => 'Terminal Report',
        self::TYPE_FEES => 'Fee Receipt / Reminder',
    ];

    protected $fillable = [
        'sms_campaign_id',
        'type',
        'student_id',
        'phone',
        'message',
        'success',
        'response_code',
        'response_message',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SmsCampaign::class, 'sms_campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

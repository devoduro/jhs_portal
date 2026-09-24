<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Exeat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'reason',
        'start_date',
        'end_date',
        'issued_by',
        'sms_log_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the student this exeat was granted to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user (house master or admin) who issued this exeat.
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the parent-notification SMS log entry sent for this exeat, if any.
     */
    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(SmsLog::class);
    }

    /**
     * Human-readable status derived from today's date against the exeat's
     * date range - "Upcoming" before it starts, "Active" while the student
     * is authorized to be off campus, "Completed" once the range has passed.
     */
    public function status(): string
    {
        $today = Carbon::today();

        if ($today->lt($this->start_date)) {
            return 'Upcoming';
        }

        if ($today->gt($this->end_date)) {
            return 'Completed';
        }

        return 'Active';
    }
}

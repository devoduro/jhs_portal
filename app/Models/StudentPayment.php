<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPayment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'receipt_number',
        'student_id',
        'academic_year_id',
        'semester_id',
        'amount',
        'payment_method',
        'bank',
        'reference_number',
        'payment_date',
        'recorded_by',
        'notes',
        'paid_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Get the student that made the payment.
     */
    public const METHODS = [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
        'bank_transfer' => 'Bank Deposit / Transfer',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    /**
     * Every payment gets a sequential receipt number (e.g. RCT-2026-000123) the moment it is
     * saved, so a printed receipt can always be traced back to its ledger entry.
     */
    protected static function booted(): void
    {
        static::created(function (StudentPayment $payment) {
            if (!$payment->receipt_number) {
                $payment->receipt_number = 'RCT-' . now()->format('Y') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
                $payment->saveQuietly();
            }
        });
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? ucfirst(str_replace('_', ' ', (string) $this->payment_method));
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic year the payment applies to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the admin user who recorded the payment.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionApplication extends Model
{
    public const STATUS_PLACED = 'placed';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ADMITTED = 'admitted';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PLACED => 'Placed (Not Paid)',
        self::STATUS_PAYMENT_PENDING => 'Payment Pending',
        self::STATUS_PAID => 'Paid - Profile Incomplete',
        self::STATUS_SUBMITTED => 'Submitted - Awaiting Review',
        self::STATUS_ADMITTED => 'Admitted',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'index_number',
        'full_name',
        'academic_year_id',
        'programme_id',
        'boarding_status',
        'gender',
        'date_of_birth',
        'phone',
        'alternate_phone',
        'whatsapp_number',
        'email',
        'father_name',
        'mother_name',
        'father_occupation',
        'mother_occupation',
        'guardian_name',
        'address',
        'town',
        'region',
        'district',
        'digital_address',
        'ghana_card_number',
        'jhs_attended',
        'jhs_type',
        'aggregate_score',
        'nationality',
        'religion',
        'place_of_birth',
        'photo_path',
        'password',
        'status',
        'fee_amount',
        'paid_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'fee_amount' => 'decimal:2',
        ];
    }

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payments()
    {
        return $this->hasMany(AdmissionPayment::class);
    }

    public function hasPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_SUBMITTED, self::STATUS_ADMITTED], true);
    }

    public function canLogIn(): bool
    {
        return $this->hasPaid() && ! empty($this->password);
    }
}

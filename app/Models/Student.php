<?php

namespace App\Models;

use App\Support\FormLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'index_number',
        'reference_number',
        'house_id',
        'boarding_status',
        'full_name',
        'date_of_birth',
        'gender',
        'programme_id',
        'level',
        'class_group_id',
        'status',
        'graduation_year',
        'profile_photo',
        'emergency_contact_name',
        'emergency_contact_phone',
        'parent_email',
        'email',
        'phone',
        'address',
        'hometown',
        'gps_address',
        'emergency_contact_relationship',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
    ];
    
    /**
     * The pupil list filters (search, class, unplaced, gender, level, status) shared by the
     * Pupils screen and its Excel / PDF exports, so an export always matches what is on screen.
     */
    public function scopeMatching($query, \Illuminate\Http\Request $request)
    {
        return $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->search;
                $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")
                    ->orWhere('index_number', 'like', "%{$term}%")
                    ->orWhere('emergency_contact_name', 'like', "%{$term}%")
                    ->orWhere('emergency_contact_phone', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->when($request->filled('class_group_id'), fn ($q) => $q->where('class_group_id', $request->class_group_id))
            ->when($request->boolean('unplaced'), fn ($q) => $q->whereNull('class_group_id'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->gender))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->level))
            ->where('status', $request->input('status', 'active'));
    }

    /**
     * Get the programme that the student belongs to.
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * Get the class group the student is assigned to.
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * Get the house (boarding house) the student is assigned to.
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * Get the exeat (authorized leave) records for the student.
     */
    public function exeats(): HasMany
    {
        return $this->hasMany(Exeat::class);
    }

    /**
     * Whether this student boards on campus (as opposed to a day student).
     */
    public function isBoarder(): bool
    {
        return $this->boarding_status === 'boarder';
    }

    /**
     * Get the results for the student.
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
    
    /**
     * Get the user account associated with the student.
     */
    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the course registrations for the student.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Get the fee payments made by the student.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(StudentPayment::class);
    }

    /**
     * Get the biometric registrations (semester check-ins) for the student.
     */
    public function biometricRegistrations(): HasMany
    {
        return $this->hasMany(BiometricRegistration::class);
    }

    /**
     * Get the campus gate in/out attendance events for the student.
     */
    public function campusAttendances(): HasMany
    {
        return $this->hasMany(CampusAttendance::class);
    }

    /**
     * Get the arrears (debt carried forward from previous years) for the student.
     */
    public function arrears(): HasMany
    {
        return $this->hasMany(StudentArrear::class);
    }

    /**
     * Get the one-off fee charges (graduation fee, resit fee, etc.) billed directly to
     * this student, as opposed to the programme-wide fee structures.
     */
    public function feeCharges(): HasMany
    {
        return $this->hasMany(StudentFeeCharge::class);
    }

    /**
     * Term-by-term progress records (attendance, conduct, remarks) for the terminal report.
     */
    public function termReports(): HasMany
    {
        return $this->hasMany(TermReport::class);
    }

    /**
     * Everything billed to this pupil (term bills and one-off charges), optionally narrowed
     * to one academic year or one term.
     */
    public function billedAmount(?AcademicYear $academicYear = null, ?Semester $term = null): float
    {
        $query = $this->feeCharges();

        if ($term) {
            $query->where('semester_id', $term->id);
        } elseif ($academicYear) {
            $query->where('academic_year_id', $academicYear->id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Payments received, optionally narrowed to one academic year or one term.
     */
    public function paidAmount(?AcademicYear $academicYear = null, ?Semester $term = null): float
    {
        $query = $this->payments();

        if ($term) {
            $query->where('semester_id', $term->id);
        } elseif ($academicYear) {
            $query->where('academic_year_id', $academicYear->id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * What the pupil owes right now across every term: all bills + arrears brought forward
     * - all payments. Negative means the pupil is in credit.
     */
    public function outstandingBalance(): float
    {
        return $this->billedAmount()
            + (float) $this->arrears()->sum('amount')
            - $this->paidAmount();
    }

    /**
     * Balance brought forward into a term - the outstanding balance excluding that term's
     * own bill and payments (i.e. what was still owed from earlier terms).
     */
    public function balanceBroughtForward(Semester $term): float
    {
        return $this->outstandingBalance() - ($this->billedAmount(null, $term) - $this->paidAmount(null, $term));
    }

    /**
     * The fee structure lines (tuition, feeding, PTA levy...) that apply to this pupil's
     * class level for a term.
     */
    public function applicableFeeItems(Semester $term): \Illuminate\Support\Collection
    {
        if ($this->level === null) {
            return collect();
        }

        return FeeStructure::where('semester_id', $term->id)
            ->where('level', $this->level)
            ->get();
    }

    /**
     * Amount billed for an academic year (all terms, all fee items).
     */
    public function tuitionFeeAmount(AcademicYear $academicYear): float
    {
        return $this->billedAmount($academicYear);
    }

    public function totalPaid(AcademicYear $academicYear): float
    {
        return $this->paidAmount($academicYear);
    }

    /**
     * Kept for older screens: the overall outstanding balance (not limited to the year).
     */
    public function feeBalance(?AcademicYear $academicYear = null): float
    {
        return $this->outstandingBalance();
    }

    /**
     * Arrears carried in from before the current academic year.
     */
    public function totalArrears(): float
    {
        $currentYear = AcademicYear::where('is_current', true)->first();

        if (!$currentYear) {
            return $this->outstandingBalance();
        }

        return $this->outstandingBalance() - ($this->billedAmount($currentYear) - $this->paidAmount($currentYear));
    }

    public function paymentPercentage(AcademicYear $academicYear): float
    {
        $bill = $this->billedAmount($academicYear);

        if ($bill <= 0) {
            return 0.0;
        }

        return round(($this->paidAmount($academicYear) / $bill) * 100, 2);
    }

    /**
     * Basic schools don't gate anything on fees - kept so older call sites keep working.
     */
    public function meetsRegistrationThreshold(Semester $semester): bool
    {
        return true;
    }

    public function levelLabel(): string
    {
        return FormLabel::label($this->level);
    }

    /**
     * BECE-style aggregate for a JHS pupil in one term: the grade values (1 best - 9 worst)
     * of the four core subjects (English, Maths, Science, Social Studies) plus the best two
     * other subjects. Null when the pupil isn't in JHS or has fewer than six graded subjects.
     */
    public function calculateAggregate(?int $semesterId = null, ?int $academicYearId = null): ?int
    {
        if (!FormLabel::isJhs($this->level)) {
            return null;
        }

        $results = $this->bestSixResults($semesterId, $academicYearId);

        if ($results->count() < 6) {
            return null;
        }

        return (int) $results->sum('grade_point');
    }

    /**
     * The results counted toward the BECE-style aggregate: all core subjects (up to four)
     * plus the best of the rest, six in total.
     */
    public function bestSixResults(?int $semesterId = null, ?int $academicYearId = null): \Illuminate\Support\Collection
    {
        $query = $this->results()->with('subject')->whereNotNull('grade_point');

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $results = $query->get();

        $core = $results->filter(fn ($r) => $r->subject?->is_core)->sortBy('grade_point')->take(4);
        $others = $results->reject(fn ($r) => $core->contains('id', $r->id))->sortBy('grade_point');

        return $core->concat($others->take(6 - $core->count()))->values();
    }

    /**
     * Kept for older screens - basic schools report positions, not WASSCE divisions.
     */
    public function getGradeStanding(): ?string
    {
        return null;
    }
}

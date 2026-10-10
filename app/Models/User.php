<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STUDENT = 'student';
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (!in_array($user->role, [self::ROLE_ADMIN, self::ROLE_STUDENT])) {
                throw new \InvalidArgumentException('Invalid user role');
            }
        });
        
        static::updating(function ($user) {
            if (!in_array($user->role, [self::ROLE_ADMIN, self::ROLE_STUDENT])) {
                throw new \InvalidArgumentException('Invalid user role');
            }
            // Prevent changing role from student to admin
            if ($user->getOriginal('role') === self::ROLE_STUDENT && $user->role === self::ROLE_ADMIN) {
                throw new \InvalidArgumentException('Cannot change role from student to admin');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_photo',
        'password',
        'role',
        'student_id',
        'teacher_id',
        'first_login',
        'index_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the student associated with the user.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher profile associated with the user.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * The number a password-reset SMS goes to: the account's own phone, else the phone on the
     * linked teacher record.
     */
    public function resetPhone(): ?string
    {
        return $this->phone ?: $this->teacher?->phone;
    }

    /**
     * Find a staff account by phone number, ignoring spaces, dashes and the +233 / 0 prefix
     * (so "024 123 4567", "0241234567" and "+233241234567" all match).
     */
    public static function findStaffByPhone(string $phone): ?self
    {
        $digits = preg_replace('/\D/', '', $phone);
        $local = substr($digits, -9);

        if (strlen($local) < 9) {
            return null;
        }

        $normalise = fn (?string $value) => substr(preg_replace('/\D/', '', (string) $value), -9);

        // Numbers are stored however they were typed ("024 123 4567", "+233..."), so compare
        // digits in PHP; a school has few enough staff accounts for this to be instant.
        return static::where('role', self::ROLE_ADMIN)
            ->where(fn ($q) => $q->whereNotNull('phone')->orWhereHas('teacher', fn ($t) => $t->whereNotNull('phone')))
            ->with('teacher')
            ->get()
            ->first(fn (self $user) => $normalise($user->resetPhone()) === $local);
    }
}

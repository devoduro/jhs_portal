<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\FormLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out who a bulk SMS goes to and what each person receives.
 *
 * Audiences:
 *  - parents: the parent/guardian phone of each matching pupil (falling back to the pupil's
 *    own phone), filtered by section, class, level, gender and fee balance;
 *  - staff:   staff accounts / teachers with a mobile number.
 *
 * Messages can carry placeholders that are filled in per recipient. When a message has no
 * pupil-specific placeholder, a parent with several children at the school gets it once.
 */
class BulkSmsService
{
    public const PLACEHOLDERS = [
        '{parent}' => "Parent/guardian's name",
        '{pupil}' => "Pupil's full name",
        '{first_name}' => "Pupil's first name",
        '{class}' => 'Class (e.g. Basic 4A)',
        '{balance}' => 'Fees owing, e.g. GHS 450.00',
        '{admission_no}' => 'Admission number',
        '{school}' => 'School name',
        '{name}' => "Staff member's name (staff messages)",
    ];

    private const PUPIL_PLACEHOLDERS = ['{pupil}', '{first_name}', '{class}', '{balance}', '{admission_no}'];

    /**
     * Pupils matching the filters, each with a computed `balance` (all bills + arrears -
     * payments) for fee filters and the {balance} placeholder.
     */
    public function pupilQuery(array $filters): Builder
    {
        $balance = '(COALESCE((SELECT SUM(amount) FROM student_fee_charges c WHERE c.student_id = students.id), 0)
            + COALESCE((SELECT SUM(amount) FROM student_arrears a WHERE a.student_id = students.id), 0)
            - COALESCE((SELECT SUM(amount) FROM student_payments p WHERE p.student_id = students.id), 0))';

        $query = Student::query()
            ->select('students.*')
            ->selectRaw("{$balance} as balance")
            ->with('classGroup')
            ->where('status', 'active');

        if (!empty($filters['section'])) {
            $levels = collect(FormLabel::OPTIONS)->keys()->filter(fn ($l) => FormLabel::section($l) === $filters['section'])->all();
            $query->whereIn('level', $levels);
        }

        if (!empty($filters['class_group_ids'])) {
            $query->whereIn('class_group_id', (array) $filters['class_group_ids']);
        }

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (($filters['fees'] ?? '') === 'owing') {
            $query->whereRaw("{$balance} > ?", [max(0.009, (float) ($filters['min_balance'] ?? 0))]);
        } elseif (($filters['fees'] ?? '') === 'paid') {
            $query->whereRaw("{$balance} <= 0.009");
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('index_number', 'like', "%{$search}%")
                ->orWhere('emergency_contact_name', 'like', "%{$search}%")
                ->orWhere('emergency_contact_phone', 'like', "%{$search}%"));
        }

        if (!empty($filters['student_ids'])) {
            $query->whereIn('students.id', (array) $filters['student_ids']);
        }

        return $query->orderBy('level')->orderBy('full_name');
    }

    public static function parentPhone(Student $student): ?string
    {
        $phone = trim((string) ($student->emergency_contact_phone ?: $student->phone));

        return strlen(preg_replace('/\D/', '', $phone)) >= 9 ? $phone : null;
    }

    public function staffQuery(array $filters): Builder
    {
        $query = User::query()->with('teacher')->where('role', User::ROLE_ADMIN);

        if (!empty($filters['staff_role'])) {
            $query->role($filters['staff_role']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('name');
    }

    /**
     * The final list of messages: [phone, message, student_id|null, name].
     *
     * @return Collection<int, array{phone: string, message: string, student_id: ?int, name: string}>
     */
    public function buildMessages(array $filters, string $template): Collection
    {
        $school = Setting::get('institution_name', 'the school');

        if (($filters['audience'] ?? 'parents') === 'staff') {
            return $this->staffQuery($filters)->get()
                ->map(fn (User $u) => ['phone' => $u->resetPhone(), 'user' => $u])
                ->filter(fn ($r) => strlen(preg_replace('/\D/', '', (string) $r['phone'])) >= 9)
                ->unique(fn ($r) => substr(preg_replace('/\D/', '', $r['phone']), -9))
                ->map(fn ($r) => [
                    'phone' => $r['phone'],
                    'message' => strtr($template, ['{name}' => $r['user']->name, '{school}' => $school, '{parent}' => $r['user']->name]),
                    'student_id' => null,
                    'name' => $r['user']->name,
                ])->values();
        }

        $messages = $this->pupilQuery($filters)->get()
            ->filter(fn (Student $s) => self::parentPhone($s))
            ->map(fn (Student $s) => [
                'phone' => self::parentPhone($s),
                'message' => $this->personalise($template, $s, $school),
                'student_id' => $s->id,
                'name' => $s->full_name,
            ]);

        // Siblings share a parent's phone: send a general message once per phone.
        if (!$this->isPupilSpecific($template)) {
            $messages = $messages->unique(fn ($m) => substr(preg_replace('/\D/', '', $m['phone']), -9));
        }

        return $messages->values();
    }

    public function personalise(string $template, Student $student, ?string $school = null): string
    {
        $names = preg_split('/\s+/', trim($student->full_name));

        return strtr($template, [
            '{parent}' => $student->emergency_contact_name ?: 'Parent',
            '{pupil}' => $student->full_name,
            '{first_name}' => count($names) > 1 ? $names[1] : $names[0],
            '{class}' => $student->classGroup->name ?? FormLabel::label($student->level),
            '{balance}' => 'GHS ' . number_format(max(0, (float) ($student->balance ?? $student->outstandingBalance())), 2),
            '{admission_no}' => $student->index_number,
            '{school}' => $school ?? Setting::get('institution_name', 'the school'),
            '{name}' => $student->emergency_contact_name ?: 'Parent',
        ]);
    }

    public function isPupilSpecific(string $template): bool
    {
        return collect(self::PUPIL_PLACEHOLDERS)->contains(fn ($p) => str_contains($template, $p));
    }

    /**
     * Number of SMS parts a message uses (160 characters for one, 153 per part after that).
     */
    public static function parts(string $message): int
    {
        $length = mb_strlen($message);

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}

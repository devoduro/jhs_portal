<?php

namespace App\Services;

use App\Mail\ParentFeesMail;
use App\Mail\ParentResultsMail;
use App\Models\EmailLog;
use App\Models\Semester;
use App\Models\SmsLog;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sends a pupil's terminal results or fee statement to their parent by SMS, email or both.
 * SMS goes to the parent phone and email to the parent email on the pupil's record; each send
 * is logged (SmsLog / EmailLog) so failures can be traced.
 *
 * Every send returns one outcome per channel:
 *  - sent:       delivered to the gateway / mail server
 *  - failed:     tried and failed (see the log)
 *  - no_contact: the parent has no usable phone / email on file
 *  - nothing:    nothing to report for that term (no scores recorded, or nothing billed yet)
 */
class ParentNotifier
{
    public const KIND_RESULTS = 'results';
    public const KIND_FEES = 'fees';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';

    private const LINK_LIFETIME_DAYS = 30;

    public function __construct(
        protected PastechSmsService $sms,
        protected SmsReceiptService $receipts,
    ) {
    }

    /**
     * @param  array<int, string>  $channels  any of 'sms', 'email'
     * @return array<string, string>  channel => outcome
     */
    public function send(Student $student, Semester $term, string $kind, array $channels): array
    {
        $outcome = [];

        foreach ($channels as $channel) {
            $outcome[$channel] = match (true) {
                $kind === self::KIND_RESULTS && $channel === self::CHANNEL_SMS => $this->resultsBySms($student, $term),
                $kind === self::KIND_RESULTS && $channel === self::CHANNEL_EMAIL => $this->resultsByEmail($student, $term),
                $kind === self::KIND_FEES && $channel === self::CHANNEL_SMS => $this->feesBySms($student, $term),
                $kind === self::KIND_FEES && $channel === self::CHANNEL_EMAIL => $this->feesByEmail($student, $term),
                default => 'nothing',
            };
        }

        return $outcome;
    }

    public static function parentEmail(Student $student): ?string
    {
        $email = trim((string) $student->parent_email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function hasResults(Student $student, Semester $term): bool
    {
        return $student->results()->where('semester_id', $term->id)->exists();
    }

    private function resultsBySms(Student $student, Semester $term): string
    {
        if (!self::hasResults($student, $term)) {
            return 'nothing';
        }
        if (!BulkSmsService::parentPhone($student) || !$student->emergency_contact_phone) {
            return 'no_contact';
        }

        $logId = GradeNotificationService::sendToParent($student, $term, $this->sms);

        return $logId && SmsLog::whereKey($logId)->value('success') ? 'sent' : 'failed';
    }

    private function feesBySms(Student $student, Semester $term): string
    {
        if (!$this->hasFeeActivity($student, $term)) {
            return 'nothing';
        }
        if (!BulkSmsService::parentPhone($student)) {
            return 'no_contact';
        }

        return $this->receipts->sendStatement($student, $term) ? 'sent' : 'failed';
    }

    private function resultsByEmail(Student $student, Semester $term): string
    {
        if (!self::hasResults($student, $term)) {
            return 'nothing';
        }
        $email = self::parentEmail($student);
        if (!$email) {
            return 'no_contact';
        }

        $classGroup = TerminalReportService::classFor($student, $term);
        if (!$classGroup) {
            return 'nothing';
        }

        // Positions are worked out once per class and term, then reused for every pupil in it.
        static $cache = [];
        $key = $classGroup->id . '-' . $term->id;
        $cache[$key] ??= TerminalReportService::forClass($classGroup, $term);
        $report = $cache[$key]['pupils']->first(fn ($p) => $p['student']->id === $student->id);

        if (!$report) {
            return 'nothing';
        }

        $link = URL::temporarySignedRoute('report-card.show', now()->addDays(self::LINK_LIFETIME_DAYS), ['student' => $student->id, 'semester_id' => $term->id]);
        $student->loadMissing('classGroup');

        return $this->mail($student, $term, EmailLog::TYPE_RESULTS, $email, new ParentResultsMail($student, $term, $report, $cache[$key]['roll'], $link));
    }

    private function feesByEmail(Student $student, Semester $term): string
    {
        if (!$this->hasFeeActivity($student, $term)) {
            return 'nothing';
        }
        $email = self::parentEmail($student);
        if (!$email) {
            return 'no_contact';
        }

        $student->loadMissing('classGroup');
        $term->loadMissing('academicYear');

        $mailable = new ParentFeesMail(
            $student,
            $term,
            TerminalReportService::feeSummary($student, $term),
            $student->feeCharges()->where('semester_id', $term->id)->orderBy('id')->get(),
            $student->payments()->where('semester_id', $term->id)->orderBy('payment_date')->orderBy('id')->get(),
        );

        return $this->mail($student, $term, EmailLog::TYPE_FEES, $email, $mailable);
    }

    private function mail(Student $student, Semester $term, string $type, string $email, $mailable): string
    {
        $error = null;

        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
            $error = mb_substr($e->getMessage(), 0, 500);
        }

        EmailLog::create([
            'student_id' => $student->id,
            'semester_id' => $term->id,
            'type' => $type,
            'email' => $email,
            'subject' => $mailable->envelope()->subject,
            'success' => $error === null,
            'error' => $error,
            'sent_by' => Auth::id(),
        ]);

        return $error === null ? 'sent' : 'failed';
    }

    /**
     * Whether a fee message has anything to say: something billed or paid this term, or a
     * balance still owing from before.
     */
    private function hasFeeActivity(Student $student, Semester $term): bool
    {
        return abs($student->billedAmount(null, $term)) > 0.004
            || abs($student->paidAmount(null, $term)) > 0.004
            || abs($student->outstandingBalance()) > 0.004;
    }
}

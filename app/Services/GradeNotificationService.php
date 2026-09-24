<?php

namespace App\Services;

use App\Models\Semester;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use App\Services\TerminalReportService;
use Illuminate\Support\Facades\URL;

class GradeNotificationService
{
    /**
     * How long a texted report-card link stays valid before its signature expires.
     */
    protected const LINK_LIFETIME_DAYS = 30;

    /**
     * "Position: 3rd out of 28. Average: 71.5%." - positions are worked out once per class and
     * reused for every pupil in it when a whole term is being texted.
     */
    protected static function summary(Student $student, Semester $semester): string
    {
        static $classCache = [];

        $classGroup = TerminalReportService::classFor($student, $semester);

        if (!$classGroup) {
            return '';
        }

        $key = $classGroup->id . '-' . $semester->id;
        $classCache[$key] ??= TerminalReportService::forClass($classGroup, $semester);
        $pupil = $classCache[$key]['pupils']->first(fn ($p) => $p['student']->id === $student->id);

        if (!$pupil || $pupil['average'] === null) {
            return '';
        }

        $text = 'Position: ' . TerminalReportService::ordinal($pupil['position']) . ' out of ' . $classCache[$key]['roll'] . '. Average: ' . $pupil['average'] . '%.';

        if ($pupil['aggregate'] !== null) {
            $text .= ' Aggregate: ' . $pupil['aggregate'] . '.';
        }

        return $text;
    }

    /**
     * Text a student's emergency contact a summary of their results for a semester,
     * plus a secure link to the full report card (no login required). Returns the
     * created SmsLog id, or null if there was no phone to text or nothing to report.
     */
    public static function sendToParent(Student $student, Semester $semester, PastechSmsService $sms): ?int
    {
        $phone = $student->emergency_contact_phone;

        if (!$phone) {
            return null;
        }

        $results = $student->results()->where('semester_id', $semester->id)->get();

        if ($results->isEmpty()) {
            return null;
        }

        try {
            $institutionName = Setting::get('institution_name', 'the school');
            $link = URL::temporarySignedRoute('report-card.show', now()->addDays(self::LINK_LIFETIME_DAYS), ['student' => $student->id, 'semester_id' => $semester->id]);

            $summary = self::summary($student, $semester);

            $message = "Dear Parent, {$student->full_name}'s {$semester->name} terminal report is ready. {$summary}"
                . ($semester->next_term_begins ? ' Next term begins ' . $semester->next_term_begins->format('d/m/Y') . '.' : '')
                . " View/print: {$link} - {$institutionName}";

            $result = $sms->sendSms($phone, $message);

            $smsLog = SmsLog::create([
                'sms_campaign_id' => null,
                'type' => SmsLog::TYPE_GRADES,
                'student_id' => $student->id,
                'phone' => $phone,
                'message' => $message,
                'success' => $result['success'] ?? false,
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['message'] ?? null,
            ]);

            return $smsLog->id;
        } catch (\Throwable $e) {
            Log::warning("Grades notification SMS failed for student {$student->id}: " . $e->getMessage());

            return null;
        }
    }
}

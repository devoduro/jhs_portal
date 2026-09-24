<?php

namespace App\Services;

use App\Models\Semester;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class GradeNotificationService
{
    /**
     * How long a texted report-card link stays valid before its signature expires.
     */
    protected const LINK_LIFETIME_DAYS = 14;

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
            $aggregate = $student->calculateAggregate($semester->id);
            $link = URL::temporarySignedRoute('report-card.show', now()->addDays(self::LINK_LIFETIME_DAYS), ['student' => $student->id]);

            $summary = $aggregate !== null
                ? "Aggregate: {$aggregate}."
                : count($results) . ' subject(s) graded so far.';

            $message = "Dear Parent/Guardian, {$student->full_name}'s ({$student->index_number}) results for {$semester->name} are ready. {$summary} View the full report card: {$link} - {$institutionName}";

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

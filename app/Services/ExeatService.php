<?php

namespace App\Services;

use App\Models\Exeat;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class ExeatService
{
    /**
     * Create an exeat and text the student's emergency contact the reason and
     * dates. Never lets an SMS failure block the exeat itself from being recorded.
     */
    public static function create(Student $student, string $reason, string $startDate, string $endDate, ?int $issuedByUserId, PastechSmsService $sms): Exeat
    {
        $smsLogId = self::notifyParent($student, $reason, $startDate, $endDate, $sms);

        return Exeat::create([
            'student_id' => $student->id,
            'reason' => $reason,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'issued_by' => $issuedByUserId,
            'sms_log_id' => $smsLogId,
        ]);
    }

    /**
     * Text the student's emergency contact that an exeat has been granted.
     */
    protected static function notifyParent(Student $student, string $reason, string $startDate, string $endDate, PastechSmsService $sms): ?int
    {
        $phone = $student->emergency_contact_phone;

        if (!$phone) {
            return null;
        }

        try {
            $institutionName = Setting::get('institution_name', 'the school');
            $start = date('M j, Y', strtotime($startDate));
            $end = date('M j, Y', strtotime($endDate));

            $message = "Dear Parent/Guardian, {$student->full_name} ({$student->index_number}) has been granted exeat from {$start} to {$end}. Reason: {$reason}. - {$institutionName}";

            $result = $sms->sendSms($phone, $message);

            $smsLog = SmsLog::create([
                'sms_campaign_id' => null,
                'type' => SmsLog::TYPE_EXEAT,
                'student_id' => $student->id,
                'phone' => $phone,
                'message' => $message,
                'success' => $result['success'] ?? false,
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['message'] ?? null,
            ]);

            return $smsLog->id;
        } catch (\Throwable $e) {
            Log::warning("Exeat SMS failed for student {$student->id}: " . $e->getMessage());

            return null;
        }
    }
}

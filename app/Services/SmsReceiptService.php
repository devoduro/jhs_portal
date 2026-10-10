<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\StudentPayment;
use Illuminate\Support\Facades\Log;

/**
 * Fee SMS to a pupil's parent/guardian: a receipt when a payment is recorded, and balance
 * reminders sent in bulk from the fee list.
 */
class SmsReceiptService
{
    public function __construct(protected PastechSmsService $sms)
    {
    }

    public function send(StudentPayment $payment): bool
    {
        $student = $payment->student;
        $school = Setting::get('institution_name', 'the school');
        $balance = $student->outstandingBalance();

        $message = "Dear Parent, we have received GHS " . number_format($payment->amount, 2)
            . " for {$student->full_name}"
            . ($payment->semester ? " ({$payment->semester->name} fees)" : '')
            . ". Receipt {$payment->receipt_number}. "
            . ($balance > 0 ? 'Balance owing: GHS ' . number_format($balance, 2) . '. ' : 'Fees fully paid. ')
            . "Thank you. - {$school}";

        return $this->deliver($student, $message);
    }

    public function sendReminder(Student $student): bool
    {
        $balance = $student->outstandingBalance();

        if ($balance <= 0) {
            return false;
        }

        $school = Setting::get('institution_name', 'the school');
        $message = "Dear Parent, this is a reminder that {$student->full_name}'s outstanding school fees balance is GHS "
            . number_format($balance, 2) . ". Kindly settle it at the school's accounts office. Thank you. - {$school}";

        return $this->deliver($student, $message);
    }

    private function deliver(Student $student, string $message): bool
    {
        $phone = $student->emergency_contact_phone ?: $student->phone;

        if (!$phone) {
            return false;
        }

        try {
            $result = $this->sms->sendSms($phone, $message);

            SmsLog::create([
                'sms_campaign_id' => null,
                'type' => SmsLog::TYPE_FEES,
                'student_id' => $student->id,
                'phone' => $phone,
                'message' => $message,
                'success' => $result['success'] ?? false,
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['message'] ?? null,
            ]);

            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning("Fee SMS failed for student {$student->id}: " . $e->getMessage());

            return false;
        }
    }
}

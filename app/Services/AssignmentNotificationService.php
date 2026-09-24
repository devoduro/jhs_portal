<?php

namespace App\Services;

use App\Mail\AssignmentPostedMail;
use App\Models\Assignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every eligible student when a teacher posts an assignment. Sent
 * synchronously (see AssignmentPostedMail) and one student at a time so a
 * single bad address or SMTP hiccup doesn't stop the rest of the class from
 * being notified.
 */
class AssignmentNotificationService
{
    public function notify(Assignment $assignment): array
    {
        $students = $assignment->eligibleStudents()->whereNotNull('email')->where('email', '!=', '')->get();

        $sent = 0;
        $failed = 0;

        foreach ($students as $student) {
            try {
                Mail::to($student->email)->send(new AssignmentPostedMail($assignment, $student));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Assignment notification email failed for student {$student->id}: ".$e->getMessage());
            }
        }

        $assignment->update(['notified_at' => now()]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_no_email' => $assignment->eligibleStudents()->count() - $students->count(),
        ];
    }
}

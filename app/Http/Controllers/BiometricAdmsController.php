<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\BiometricLog;
use App\Models\BiometricRegistration;
use App\Models\CampusAttendance;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles the ZKTeco ADMS/PUSH protocol endpoints that biometric devices
 * call directly over HTTP (no authentication - the device cannot log in).
 *
 * These endpoints follow the conventional paths ZKTeco firmware is hardcoded
 * (or commonly configured) to call: /iclock/cdata and /iclock/getrequest.
 */
class BiometricAdmsController extends Controller
{
    /**
     * A punch from the same student within this many minutes of their last
     * recorded gate event is treated as a duplicate scan, not a new event
     * (e.g. a student tapping twice because the first read looked uncertain).
     */
    protected const DEBOUNCE_MINUTES = 2;

    /**
     * Device handshake / options request: GET /iclock/cdata?SN=...&options=all
     * Also handles the device pushing attendance data via GET with a "table" query param
     * on some firmware versions, though POST is the common case (see store()).
     */
    public function handshake(Request $request): Response
    {
        $serial = $request->query('SN');

        if ($serial) {
            $device = BiometricDevice::firstOrCreate(
                ['serial_number' => $serial],
                ['name' => $serial]
            );
            $device->update(['last_seen_at' => now()]);
        }

        $body = implode("\r\n", [
            "GET OPTION FROM: {$serial}",
            'Stamp=9999',
            'OpStamp=9999',
            'ErrorDelay=60',
            'Delay=30',
            'TransFlag=1111000000',
            'TransInterval=1',
            'Realtime=1',
            'Encrypt=0',
        ]) . "\r\n";

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Attendance push: POST /iclock/cdata?SN=...&table=ATTLOG
     * Body is raw tab-separated lines: PIN\tTIME\tSTATUS\tVERIFY...
     */
    public function store(Request $request, PastechSmsService $sms): Response
    {
        $serial = $request->query('SN');
        $table = $request->query('table');

        $device = null;
        if ($serial) {
            $device = BiometricDevice::firstOrCreate(
                ['serial_number' => $serial],
                ['name' => $serial]
            );
            $device->update(['last_seen_at' => now()]);
        }

        $processed = 0;

        if ($table === 'ATTLOG') {
            $lines = preg_split('/\r\n|\r|\n/', trim($request->getContent()));

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $processed += $this->ingestAttendanceLine($line, $device, $sms);
            }
        }

        return response("OK: {$processed}", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Command polling: GET /iclock/getrequest?SN=...
     * We don't queue remote commands for the device, so always respond with no-op.
     */
    public function getRequest(Request $request): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Parse a single ATTLOG line and fan it out to the two things a campus
     * punch feeds: the semester course-registration biometric gate (unchanged,
     * pre-existing behaviour), and campus gate in/out attendance with a
     * parent SMS on check-in (new). Classroom attendance is entered manually
     * by teachers via the CA forms and is not derived from device punches here.
     */
    protected function ingestAttendanceLine(string $line, ?BiometricDevice $device, PastechSmsService $sms): int
    {
        $fields = preg_split('/\t/', $line);
        $devicePin = trim($fields[0] ?? '');
        $rawTime = trim($fields[1] ?? '');
        $verifyMode = trim($fields[3] ?? '') ?: null;

        if ($devicePin === '' || $rawTime === '') {
            return 0;
        }

        $punchedAt = strtotime($rawTime);
        if ($punchedAt === false) {
            return 0;
        }

        $punchedAtFormatted = date('Y-m-d H:i:s', $punchedAt);
        $student = Student::where('index_number', $devicePin)->first();

        $log = BiometricLog::create([
            'biometric_device_id' => $device?->id,
            'device_user_id' => $devicePin,
            'punched_at' => $punchedAtFormatted,
            'verify_mode' => $verifyMode,
            'raw_payload' => $line,
            'student_id' => $student?->id,
        ]);

        if ($student) {
            $this->markSemesterBiometricRegistration($student, $log);
            $this->recordCampusAttendance($student, $device, $log, $punchedAtFormatted, $sms);
        }

        return 1;
    }

    /**
     * Mark the student's current semester as biometrically verified for course
     * registration purposes, if that window is currently open. Unrelated to
     * campus gate attendance - a student can be gate-verified in/out many
     * times a day, but this per-semester flag is only ever set once.
     */
    protected function markSemesterBiometricRegistration(Student $student, BiometricLog $log): void
    {
        $currentSemester = Semester::where('is_current', true)
            ->where('biometric_window_open', true)
            ->first();

        if (!$currentSemester) {
            return;
        }

        BiometricRegistration::firstOrCreate(
            [
                'student_id' => $student->id,
                'semester_id' => $currentSemester->id,
            ],
            [
                'verified_at' => $log->punched_at,
                'source' => 'device',
                'biometric_log_id' => $log->id,
            ]
        );
    }

    /**
     * Record a campus gate in/out event for the student and, on check-in,
     * text their emergency contact.
     *
     * Direction is deliberately NOT derived from the device's own STATUS
     * field: many standalone ZKTeco terminals configured for open/"self
     * service" attendance always report status 0 for every punch regardless
     * of direction, which would make every event look like a check-in. We
     * instead alternate based on the student's own last recorded direction,
     * which is reliable regardless of how the device is configured.
     */
    protected function recordCampusAttendance(
        Student $student,
        ?BiometricDevice $device,
        BiometricLog $log,
        string $punchedAtFormatted,
        PastechSmsService $sms
    ): void {
        $lastEvent = CampusAttendance::where('student_id', $student->id)
            ->latest('occurred_at')
            ->first();

        if ($lastEvent && $lastEvent->occurred_at->diffInMinutes($punchedAtFormatted) < self::DEBOUNCE_MINUTES) {
            // Same student scanning again within the debounce window - treat as a duplicate read.
            return;
        }

        $direction = ($lastEvent && $lastEvent->direction === 'in') ? 'out' : 'in';

        $smsLogId = null;
        if ($direction === 'in') {
            $smsLogId = $this->notifyParentOfCheckIn($student, $punchedAtFormatted, $sms);
        }

        CampusAttendance::create([
            'student_id' => $student->id,
            'biometric_device_id' => $device?->id,
            'biometric_log_id' => $log->id,
            'direction' => $direction,
            'occurred_at' => $punchedAtFormatted,
            'sms_log_id' => $smsLogId,
        ]);
    }

    /**
     * Text the student's emergency contact that they've checked onto campus.
     * Never lets an SMS failure break attendance recording - the device push
     * must still get its "OK" response either way.
     */
    protected function notifyParentOfCheckIn(Student $student, string $punchedAtFormatted, PastechSmsService $sms): ?int
    {
        $phone = $student->emergency_contact_phone;

        if (!$phone) {
            return null;
        }

        try {
            $institutionName = Setting::get('institution_name', 'the school');
            $time = date('g:i A', strtotime($punchedAtFormatted));
            $date = date('D, M j', strtotime($punchedAtFormatted));

            $message = "Dear Parent/Guardian, {$student->full_name} ({$student->index_number}) has checked IN to campus at {$time} on {$date}. - {$institutionName}";

            $result = $sms->sendSms($phone, $message);

            $smsLog = SmsLog::create([
                'sms_campaign_id' => null,
                'type' => SmsLog::TYPE_CAMPUS_CHECKIN,
                'student_id' => $student->id,
                'phone' => $phone,
                'message' => $message,
                'success' => $result['success'] ?? false,
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['message'] ?? null,
            ]);

            return $smsLog->id;
        } catch (\Throwable $e) {
            Log::warning("Campus check-in SMS failed for student {$student->id}: " . $e->getMessage());

            return null;
        }
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\StudentPasswordResetMail;
use App\Models\Student;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentPasswordResetController extends Controller
{
    // ──────────────────────────────────────────────
    // Step 1 – show the request form (SMS or email)
    // ──────────────────────────────────────────────
    public function showRequestForm()
    {
        return view('auth.student-forgot-password');
    }

    // ──────────────────────────────────────────────
    // Step 2a – verify phone, generate token, send SMS
    // ──────────────────────────────────────────────
    public function sendResetLink(Request $request, PastechSmsService $sms)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = $request->phone;

        // Find student by phone number
        $student = Student::where('phone', $phone)->first();

        if (! $student) {
            return back()->withErrors(['phone' => 'No student account found with that phone number.'])->withInput();
        }

        // Find the user account linked to this student
        $user = User::where('student_id', $student->id)->first();

        if (! $user) {
            return back()->withErrors(['phone' => 'No login account exists for this student. Contact the administrator.'])->withInput();
        }

        $resetUrl = $this->issueResetToken($user);

        $message = "Hello {$student->full_name},\n\nUse the link below to reset your school portal password. It expires in 60 minutes.\n\n{$resetUrl}\n\nIf you did not request this, ignore this message.";

        $result = $sms->sendSms($phone, $message);

        if (! $result['success']) {
            return back()->with('error', 'We could not send the SMS: ' . $result['message'])->withInput();
        }

        return back()->with('status', 'A password reset link has been sent to your phone number.');
    }

    // ──────────────────────────────────────────────
    // Step 2b – verify email, generate token, send email
    // ──────────────────────────────────────────────
    public function sendResetLinkByEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $student = Student::where('email', $request->email)->first();

        if (! $student) {
            return back()->withErrors(['email' => 'No student account found with that email address.'])->withInput();
        }

        $user = User::where('student_id', $student->id)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No login account exists for this student. Contact the administrator.'])->withInput();
        }

        $resetUrl = $this->issueResetToken($user);

        try {
            Mail::to($student->email)->send(new StudentPasswordResetMail($student, $resetUrl));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'We could not send the email. Please try again shortly or use SMS instead.')->withInput();
        }

        return back()->with('status', 'A password reset link has been sent to your email address.');
    }

    /**
     * Generate a reset token and store it the same way Laravel's own password
     * broker does (password_reset_tokens keyed by email), regardless of which
     * channel (SMS or email) ends up delivering it.
     */
    private function issueResetToken(User $user): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        return url(route('student.password.reset', ['token' => $token, 'email' => $user->email], false));
    }

    // ──────────────────────────────────────────────
    // Step 3 – show the new password form
    // ──────────────────────────────────────────────
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.student-reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    // ──────────────────────────────────────────────
    // Step 4 – validate token and update password
    // ──────────────────────────────────────────────
    public function reset(Request $request)
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        // Retrieve the stored token record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return back()->withErrors(['token' => 'This password reset link is invalid or has expired.']);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['token' => 'This password reset link has expired. Please request a new one.']);
        }

        if (! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['token' => 'This password reset link is invalid.']);
        }

        // Update the user's password
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found for this email.']);
        }

        $user->password    = Hash::make($request->password);
        $user->first_login = false;
        $user->save();

        // Remove used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('student.login')
            ->with('status', 'Your password has been reset. You can now log in.');
    }
}

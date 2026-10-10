<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\PastechSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * "Forgot password" for staff (admin, teachers, accountant, head teacher...). The staff
 * member types either their email or their mobile number:
 *  - email  -> Laravel's normal reset-link email;
 *  - phone  -> the same kind of one-time, expiring reset link, sent by SMS to the number on
 *              their account (or on their teacher record).
 *
 * The response never says whether an account exists, so the form can't be used to find out
 * who has a login. Requests are rate limited on the route.
 */
class ForgotPasswordController extends Controller
{
    public const GENERIC_STATUS = 'If an account matches what you entered, a password reset link is on its way. It expires in 60 minutes.';

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request, PastechSmsService $sms)
    {
        // Older forms post "email"; the new one posts "login" (email or phone).
        $login = trim((string) $request->input('login', $request->input('email')));
        $request->merge(['login' => $login]);
        $request->validate(['login' => 'required|string|max:255'], ['login.required' => 'Enter your email address or mobile number.']);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return $this->viaEmail($login);
        }

        if (strlen(preg_replace('/\D/', '', $login)) >= 9) {
            return $this->viaSms($login, $sms);
        }

        return back()->withInput()->withErrors(['login' => 'That is not a valid email address or mobile number.']);
    }

    private function viaEmail(string $email)
    {
        $user = User::where('email', $email)->where('role', User::ROLE_ADMIN)->first();

        if ($user) {
            try {
                $status = Password::sendResetLink(['email' => $email]);
            } catch (\Throwable $e) {
                report($e);

                return back()->withInput()->with('error', 'The email could not be sent right now. Try your mobile number instead, or ask the administrator.');
            }

            if ($status === Password::RESET_THROTTLED) {
                return back()->withInput()->withErrors(['login' => __($status)]);
            }
        }

        return back()->with('status', self::GENERIC_STATUS);
    }

    private function viaSms(string $phone, PastechSmsService $sms)
    {
        $user = User::findStaffByPhone($phone);

        if ($user) {
            $link = route('password.reset', ['token' => Password::createToken($user), 'email' => $user->email]);
            $school = Setting::get('institution_name', 'the school');

            try {
                $result = $sms->sendSms($user->resetPhone(), "{$school}: reset your portal password here (valid 60 mins): {$link} If you did not ask for this, ignore this message.");
            } catch (\Throwable $e) {
                report($e);
                $result = ['success' => false];
            }

            if (!($result['success'] ?? false)) {
                Log::warning("Password reset SMS failed for user {$user->id}", $result);

                return back()->withInput()->with('error', 'The SMS could not be sent right now. Try your email address instead, or ask the administrator.');
            }
        }

        return back()->with('status', self::GENERIC_STATUS);
    }
}

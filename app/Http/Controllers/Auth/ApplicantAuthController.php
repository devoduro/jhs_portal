<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Applicant portal auth - a small self-contained session check (see
 * EnsureApplicantIsAuthenticated), not the app's normal Auth facade/guard.
 */
class ApplicantAuthController extends Controller
{
    /**
     * Applicants set their password once, right after their payment is
     * confirmed (see AdmissionPortalController::paymentStatus), and are
     * logged straight in. This just re-shows the same form if they leave
     * and come back before finishing.
     */
    public function showSetPasswordForm(string $indexNumber)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();

        if (!$application->hasPaid()) {
            return redirect()->route('admission.verify')->with('error', 'Please complete payment first.');
        }

        if (!empty($application->password)) {
            return redirect()->route('admission.login')->with('success', 'You already set a password - please log in.');
        }

        return view('admission.set-password', compact('application'));
    }

    public function setPassword(Request $request, string $indexNumber)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();

        if (!$application->hasPaid()) {
            return redirect()->route('admission.verify')->with('error', 'Please complete payment first.');
        }

        if (!empty($application->password)) {
            return redirect()->route('admission.login')->with('success', 'You already set a password - please log in.');
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $application->update(['password' => Hash::make($request->password)]);

        $request->session()->put('admission_applicant_id', $application->id);
        $request->session()->regenerate();

        return redirect()->route('admission.dashboard')->with('success', 'Password set. Welcome!');
    }

    public function showLoginForm()
    {
        return view('admission.login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admission.login')->withErrors($validator)->withInput();
        }

        $application = AdmissionApplication::where('index_number', $request->index_number)->first();

        if (!$application || !$application->canLogIn() || !Hash::check($request->password, $application->password)) {
            return redirect()->route('admission.login')
                ->withErrors(['index_number' => 'Invalid index number or password.'])
                ->withInput();
        }

        $request->session()->put('admission_applicant_id', $application->id);
        $request->session()->regenerate();

        return redirect()->route('admission.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admission_applicant_id');
        $request->session()->regenerateToken();

        return redirect()->route('admission.login')->with('success', 'You have been logged out.');
    }
}

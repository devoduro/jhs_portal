<?php

namespace App\Http\Middleware;

use App\Models\AdmissionApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the applicant portal. Deliberately not Laravel's Auth facade/guard
 * system - applicants are unverified, temporary identities (many never
 * complete admission) and the app's users table only allows admin/student
 * roles, so this is a small self-contained session check instead.
 */
class EnsureApplicantIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $applicantId = $request->session()->get('admission_applicant_id');
        $application = $applicantId ? AdmissionApplication::find($applicantId) : null;

        if (! $application || ! $application->canLogIn()) {
            $request->session()->forget('admission_applicant_id');

            return redirect()->route('admission.login')->with('error', 'Please log in to continue.');
        }

        $request->attributes->set('admissionApplicant', $application);

        return $next($request);
    }
}

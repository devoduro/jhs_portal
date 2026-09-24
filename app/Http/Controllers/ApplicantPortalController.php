<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Authenticated part of the admission portal (gated by the
 * admission.applicant middleware / EnsureApplicantIsAuthenticated).
 */
class ApplicantPortalController extends Controller
{
    protected function currentApplicant(Request $request): AdmissionApplication
    {
        return $request->attributes->get('admissionApplicant');
    }

    public function dashboard(Request $request)
    {
        $application = $this->currentApplicant($request)->load('programme');

        return view('admission.dashboard', compact('application'));
    }

    public function showProfileForm(Request $request)
    {
        $application = $this->currentApplicant($request)->load('programme');

        if (!in_array($application->status, [AdmissionApplication::STATUS_PAID, AdmissionApplication::STATUS_SUBMITTED], true)) {
            return redirect()->route('admission.dashboard')
                ->with('error', 'Your application can no longer be edited.');
        }

        return view('admission.complete-profile', compact('application'));
    }

    public function updateProfile(Request $request)
    {
        $application = $this->currentApplicant($request);

        if (!in_array($application->status, [AdmissionApplication::STATUS_PAID, AdmissionApplication::STATUS_SUBMITTED], true)) {
            return redirect()->route('admission.dashboard')
                ->with('error', 'Your application can no longer be edited.');
        }

        $validator = Validator::make($request->all(), [
            'gender' => 'required|in:Male,Female,Other',
            'date_of_birth' => 'required|date|before:today',
            'boarding_status' => 'required|in:day,boarder',
            'phone' => 'required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'father_occupation' => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'address' => 'required|string',
            'town' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'digital_address' => 'nullable|string|max:255',
            'ghana_card_number' => 'nullable|string|max:50',
            'jhs_attended' => 'required|string|max:255',
            'jhs_type' => 'required|in:Public,Private',
            'aggregate_score' => 'nullable|string|max:10',
            'nationality' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'place_of_birth' => 'nullable|string|max:255',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('admission_photos', 'public');
        }

        if ($request->input('action') === 'submit') {
            $data['status'] = AdmissionApplication::STATUS_SUBMITTED;
            $data['submitted_at'] = now();
        }

        $application->update($data);

        if ($request->input('action') === 'submit') {
            return redirect()->route('admission.dashboard')
                ->with('success', 'Your application has been submitted. The school will review it and confirm your admission.');
        }

        return redirect()->route('admission.profile.edit')->with('success', 'Progress saved.');
    }

    public function printSlip(Request $request)
    {
        $application = $this->currentApplicant($request)->load('programme');

        if (!in_array($application->status, [AdmissionApplication::STATUS_SUBMITTED, AdmissionApplication::STATUS_ADMITTED], true)) {
            return redirect()->route('admission.dashboard')
                ->with('error', 'Submit your application before printing a slip.');
        }

        return view('admission.print-slip', compact('application'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\AdmissionPayment;
use App\Models\Setting;
use App\Services\AdmissionPaymentService;
use App\Services\SpesoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public, no-login part of the admission portal: an applicant enters the
 * index number they were placed under, pays the admission fee via Speso
 * mobile money, then sets a password (see Auth\ApplicantAuthController) to
 * continue into the authenticated part (ApplicantPortalController).
 */
class AdmissionPortalController extends Controller
{
    public function showVerifyForm()
    {
        return view('admission.verify');
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'index_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $application = AdmissionApplication::where('index_number', $request->index_number)->first();

        if (!$application) {
            return back()
                ->withErrors(['index_number' => 'We could not find this index number. Check that you entered it correctly, or contact the school if you believe you were placed here.'])
                ->withInput();
        }

        if ($application->canLogIn()) {
            return redirect()->route('admission.login')
                ->with('success', 'You have already paid the admission fee - please log in to continue.');
        }

        return redirect()->route('admission.pay', $application->index_number);
    }

    public function showPayForm(string $indexNumber)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();

        if ($application->canLogIn()) {
            return redirect()->route('admission.login')->with('success', 'You have already paid - please log in.');
        }

        $feeAmount = (float) Setting::get('admission_fee_amount', '50');
        $pendingPayment = $application->payments()->latest()->first();

        return view('admission.pay', compact('application', 'feeAmount', 'pendingPayment'));
    }

    public function pay(Request $request, string $indexNumber)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();

        if ($application->canLogIn()) {
            return redirect()->route('admission.login')->with('success', 'You have already paid - please log in.');
        }

        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string|max:20',
            'network' => 'required|in:MTN,VOD,ATL',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $feeAmount = (float) Setting::get('admission_fee_amount', '50');

        $payment = AdmissionPayment::create([
            'admission_application_id' => $application->id,
            'order_reference' => 'ADM-'.strtoupper(Str::random(10)),
            'network' => $request->network,
            'mobile' => $request->mobile,
            'amount' => $feeAmount,
            'status' => AdmissionPayment::STATUS_PENDING,
        ]);

        try {
            SpesoClient::make()->requestCollection(
                orderId: $payment->order_reference,
                mobile: $request->mobile,
                network: $request->network,
                amount: $feeAmount,
                description: 'Admission fee - '.$application->full_name,
            );

            $payment->update(['status' => AdmissionPayment::STATUS_PROCESSING]);
            $application->update(['status' => AdmissionApplication::STATUS_PAYMENT_PENDING]);
        } catch (\Throwable $e) {
            report($e);
            $payment->update(['status' => AdmissionPayment::STATUS_FAILED]);

            return back()->withErrors(['mobile' => 'Unable to reach the payment provider right now. Please try again shortly.'])->withInput();
        }

        return redirect()->route('admission.pay.waiting', $application->index_number);
    }

    public function showWaiting(string $indexNumber)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();

        if ($application->canLogIn()) {
            return redirect()->route('admission.set-password.show', $application->index_number);
        }

        return view('admission.pay-waiting', compact('application'));
    }

    /**
     * Polled every few seconds from the waiting page. The webhook is the
     * authoritative completion path; this re-checks with Speso directly as a
     * fallback in case the webhook is delayed or unreachable, mirroring the
     * same pattern used for WAEC checker orders.
     */
    public function paymentStatus(string $indexNumber, AdmissionPaymentService $paymentService)
    {
        $application = AdmissionApplication::where('index_number', $indexNumber)->firstOrFail();
        $payment = $application->payments()->latest()->first();

        if (!$payment) {
            return response()->json(['status' => 'none']);
        }

        if ($payment->status === AdmissionPayment::STATUS_PROCESSING) {
            try {
                $result = SpesoClient::make()->getCollection($payment->order_reference);

                match ($result['status'] ?? null) {
                    'completed' => $paymentService->markCompleted($payment, $result),
                    'failed' => $paymentService->markFailed($payment),
                    default => null,
                };
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $payment->refresh();

        return response()->json([
            'status' => $payment->status,
            'redirect' => $payment->status === AdmissionPayment::STATUS_COMPLETED
                ? route('admission.set-password.show', $application->index_number)
                : null,
        ]);
    }
}

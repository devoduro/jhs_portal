<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\AdmissionPayment;

/**
 * Shared by the webhook (authoritative) and the waiting-page status poll
 * (fallback, in case the webhook is delayed or unreachable) so a payment is
 * only ever marked completed/failed in one place.
 */
class AdmissionPaymentService
{
    public function markCompleted(AdmissionPayment $payment, array $result = []): void
    {
        if ($payment->status === AdmissionPayment::STATUS_COMPLETED) {
            return;
        }

        $payment->update([
            'status' => AdmissionPayment::STATUS_COMPLETED,
            'speso_reference' => $result['speso_reference'] ?? $payment->speso_reference,
            'raw_response' => $result ?: $payment->raw_response,
            'completed_at' => now(),
        ]);

        $application = $payment->application;

        $application->update([
            'status' => AdmissionApplication::STATUS_PAID,
            'fee_amount' => $payment->amount,
            'paid_at' => now(),
        ]);
    }

    public function markFailed(AdmissionPayment $payment): void
    {
        if (in_array($payment->status, [AdmissionPayment::STATUS_COMPLETED, AdmissionPayment::STATUS_FAILED], true)) {
            return;
        }

        $payment->update(['status' => AdmissionPayment::STATUS_FAILED]);

        $application = $payment->application;

        if ($application->status === AdmissionApplication::STATUS_PAYMENT_PENDING) {
            $application->update(['status' => AdmissionApplication::STATUS_PLACED]);
        }
    }
}

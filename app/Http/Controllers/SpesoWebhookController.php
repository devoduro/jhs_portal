<?php

namespace App\Http\Controllers;

use App\Models\AdmissionPayment;
use App\Services\AdmissionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SpesoWebhookController extends Controller
{
    public function __invoke(Request $request, AdmissionPaymentService $paymentService)
    {
        if (!$this->hasValidSignature($request)) {
            Log::warning('Speso webhook received with an invalid or missing signature');

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $validated = $request->validate([
            'order_id' => ['nullable', 'string'],
            'speso_reference' => ['required', 'string'],
            'status' => ['required', 'string', 'in:processing,completed,failed'],
            'amount' => ['required', 'numeric'],
        ]);

        $orderId = $validated['order_id'] ?? null;
        $payment = $orderId ? AdmissionPayment::where('order_reference', $orderId)->first() : null;

        if (!$payment) {
            Log::warning('Speso webhook received for unknown order', ['order_id' => $orderId]);

            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Guard against replays / spoofed amounts on a payment that already resolved.
        if (in_array($payment->status, [AdmissionPayment::STATUS_COMPLETED, AdmissionPayment::STATUS_FAILED], true)) {
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        if (bccomp((string) $validated['amount'], (string) $payment->amount, 2) !== 0) {
            Log::warning('Speso webhook amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount,
                'received' => $validated['amount'],
            ]);

            return response()->json(['success' => false, 'message' => 'Amount mismatch'], 422);
        }

        match ($validated['status']) {
            'completed' => $paymentService->markCompleted($payment, $validated),
            'failed' => $paymentService->markFailed($payment),
            default => null,
        };

        return response()->json(['success' => true]);
    }

    /**
     * Verifies the `X-Speso-Signature: t=<timestamp>,v1=<hmac>` header against
     * HMAC-SHA256(t + "." + raw body, webhook secret), rejecting stale (>5min)
     * or forged callbacks. See https://business.speso.co/developer.
     */
    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.speso.webhook_secret');

        if (!$secret) {
            Log::error('SPESO_WEBHOOK_SECRET is not configured; rejecting webhook.');

            return false;
        }

        $header = (string) $request->header('X-Speso-Signature');

        if (!preg_match('/^t=(\d+),v1=([0-9a-f]+)$/', $header, $matches)) {
            return false;
        }

        [, $timestamp, $signature] = $matches;

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}

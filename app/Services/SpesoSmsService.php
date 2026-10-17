<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS gateway client for the Speso business API (https://business.speso.co/developer).
 *
 * It extends PastechSmsService so every place in the app that asks for the SMS service (password
 * resets, receipts, results, bulk SMS...) gets Speso when SMS_PROVIDER=speso, with no other code
 * changing - see AppServiceProvider. normalizePhone() is inherited.
 */
class SpesoSmsService extends PastechSmsService
{
    private string $speso_key;
    private string $speso_sender;
    private string $speso_url;

    public function __construct()
    {
        // Deliberately not calling parent::__construct(): that reads the Pastech settings.
        $this->speso_key = (string) config('services.speso_sms.api_key');
        $this->speso_sender = (string) config('services.speso_sms.sender_id');
        // `?:` so a blank SPESO_BASE_URL line or a stale config cache can't leave this empty.
        $this->speso_url = rtrim((string) (config('services.speso_sms.base_url') ?: 'https://business.speso.co/api/v1'), '/');
    }

    /**
     * Send a single SMS message.
     *
     * @return array{success: bool, code: string, message: string}
     */
    public function sendSms(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);

        if ($this->speso_key === '') {
            return ['success' => false, 'code' => 'config', 'message' => 'Speso API key is not configured. Set SPESO_API_KEY in .env.'];
        }

        try {
            $response = Http::withToken($this->speso_key)
                ->acceptJson()
                ->timeout(15)
                ->post($this->speso_url . '/messages/send', [
                    'sender_id' => $this->speso_sender,
                    'phone' => $phone,
                    'message' => $message,
                ]);

            $body = $response->json() ?? [];
            $success = $response->successful() && ($body['success'] ?? true) !== false;

            if (!$success) {
                Log::error('Speso SMS failed', ['phone' => $phone, 'status' => $response->status(), 'body' => $response->body()]);
            }

            return [
                'success' => $success,
                'code' => (string) $response->status(),
                'message' => $body['message'] ?? $body['error'] ?? ($success ? 'Message submitted successfully' : 'SMS sending failed'),
            ];
        } catch (\Throwable $e) {
            Log::error('Speso SMS exception', ['phone' => $phone, 'error' => $e->getMessage()]);

            return ['success' => false, 'code' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Speso has no balance call in this app; the balance is on the Speso dashboard.
     */
    public function checkBalance(): array
    {
        return ['success' => false, 'body' => 'Check your SMS balance on the Speso dashboard (business.speso.co).'];
    }
}

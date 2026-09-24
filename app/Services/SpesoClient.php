<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal client for the Speso mobile money collections API, used to charge
 * the admission fee. See https://business.speso.co/developer.
 */
class SpesoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('services.speso.base_url'), '/'),
            config('services.speso.api_key'),
        );
    }

    /**
     * Request a mobile money payment prompt from a customer.
     */
    public function requestCollection(
        string $orderId,
        string $mobile,
        string $network,
        float $amount,
        ?string $description = null,
        ?string $callbackUrl = null,
    ): array {
        $callbackUrl ??= config('services.speso.callback_url');

        // Speso requires a publicly reachable HTTPS URL. Drop anything else
        // (e.g. a local http:// APP_URL) rather than let Speso reject the
        // whole request - it falls back to the business's default callback,
        // and our own status polling covers delivery either way.
        if ($callbackUrl && ! str_starts_with($callbackUrl, 'https://')) {
            $callbackUrl = null;
        }

        return $this->post('/collections', array_filter([
            'order_id' => $orderId,
            'mobile' => $mobile,
            'network' => $network,
            'amount' => $amount,
            'description' => $description,
            'callback_url' => $callbackUrl,
        ], fn ($value) => ! is_null($value)));
    }

    /**
     * Check the status of a collection by order_id or speso_reference.
     */
    public function getCollection(string $reference): array
    {
        return $this->get('/collections/'.$reference);
    }

    private function post(string $path, array $payload): array
    {
        return $this->handle($this->client()->post($this->baseUrl.$path, $payload));
    }

    private function get(string $path): array
    {
        return $this->handle($this->client()->get($this->baseUrl.$path));
    }

    private function client(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('Speso API key is not configured. Set SPESO_API_KEY in .env.');
        }

        return Http::withToken($this->apiKey)->acceptJson();
    }

    private function handle(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->failed() || ($body['success'] ?? true) === false) {
            Log::error('Speso API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException($body['message'] ?? $body['error'] ?? 'Speso API request failed.');
        }

        return $body;
    }
}

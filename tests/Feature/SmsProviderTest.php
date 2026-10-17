<?php

namespace Tests\Feature;

use App\Services\PastechSmsService;
use App\Services\SpesoSmsService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsProviderTest extends TestCase
{
    /** TestCase fakes every request by default; start from a clean client to fake a specific gateway reply. */
    private function fakeGateway(array $replies): void
    {
        Http::swap(new Factory());
        Http::fake($replies);
    }

    public function test_the_provider_setting_picks_the_gateway(): void
    {
        config(['services.sms_provider' => 'speso']);
        $this->assertInstanceOf(SpesoSmsService::class, app(PastechSmsService::class));

        config(['services.sms_provider' => 'pastech']);
        $this->assertNotInstanceOf(SpesoSmsService::class, app(PastechSmsService::class));
    }

    public function test_speso_posts_to_its_api_with_the_key_and_a_local_number(): void
    {
        config(['services.sms_provider' => 'speso', 'services.speso_sms.api_key' => 'test-key', 'services.speso_sms.sender_id' => 'SCHOOL', 'services.speso_sms.base_url' => 'https://speso.test/api/v1']);
        $this->fakeGateway(['speso.test/*' => Http::response(['success' => true, 'message' => 'Queued'], 200)]);

        $result = app(PastechSmsService::class)->sendSms('+233 24 123 4567', 'Hello');

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($r) => $r->url() === 'https://speso.test/api/v1/messages/send'
            && $r->hasHeader('Authorization', 'Bearer test-key')
            && $r['phone'] === '0241234567' && $r['sender_id'] === 'SCHOOL' && $r['message'] === 'Hello');
    }

    public function test_speso_failures_and_a_missing_key_are_reported_not_thrown(): void
    {
        config(['services.sms_provider' => 'speso', 'services.speso_sms.api_key' => 'test-key', 'services.speso_sms.base_url' => 'https://speso.test/api/v1']);
        $this->fakeGateway(['speso.test/*' => Http::response(['success' => false, 'message' => 'Insufficient balance'], 402)]);
        $failed = app(PastechSmsService::class)->sendSms('0241234567', 'Hello');
        $this->assertFalse($failed['success']);
        $this->assertSame('Insufficient balance', $failed['message']);

        config(['services.speso_sms.api_key' => '']);
        $this->assertSame('config', app(PastechSmsService::class)->sendSms('0241234567', 'Hello')['code']);
    }
}

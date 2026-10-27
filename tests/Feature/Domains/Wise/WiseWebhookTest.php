<?php

namespace Tests\Feature\Domains\Wise;

use App\Domains\Wise\Jobs\ProcessWiseWebhookJob;
use App\Models\Setting;
use App\Models\WiseLiquidityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithWiseSettings;
use Tests\TestCase;

class WiseWebhookTest extends TestCase
{
    use InteractsWithWiseSettings;
    use RefreshDatabase;

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        $exported = '';
        openssl_pkey_export($resource, $exported);
        $details = openssl_pkey_get_details($resource);

        if ($exported === '' || ! is_array($details) || ! is_string($details['key'] ?? null)) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        $this->privateKey = $exported;
        $this->publicKey = $details['key'];

        $this->seedWiseSettings([
            'wise.webhook_public_key' => $this->publicKey,
        ]);
    }

    public function test_accepts_a_signed_webhook(): void
    {
        Queue::fake([ProcessWiseWebhookJob::class]);

        $body = json_encode([
            'event_type' => 'transfers#state-change',
            'data' => ['resource' => ['id' => 99]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            $this->headers($this->sign($body)),
            $body,
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertPushed(ProcessWiseWebhookJob::class);
    }

    public function test_processes_balance_update_into_liquidity_events(): void
    {
        $body = json_encode([
            'event_type' => 'balances#update',
            'data' => [
                'balance_id' => 111,
                'currency' => 'EUR',
                'amount' => 70.5,
                'channel_name' => 'TRANSFER',
                'occurred_at' => '2023-03-08T14:55:38.123Z',
                'post_transaction_balance_amount' => 310.86,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            array_merge($this->headers($this->sign($body)), [
                'HTTP_X_DELIVERY_ID' => 'delivery-balance-1',
            ]),
            $body,
        )->assertOk();

        $this->assertDatabaseHas('wise_liquidity_events', [
            'event_type' => 'balances#update',
            'delivery_id' => 'delivery-balance-1',
            'currency' => 'EUR',
            'amount_cents' => 7050,
            'channel_name' => 'TRANSFER',
        ]);

        $this->assertSame(1, WiseLiquidityEvent::query()->count());

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            array_merge($this->headers($this->sign($body)), [
                'HTTP_X_DELIVERY_ID' => 'delivery-balance-1',
            ]),
            $body,
        )->assertOk();

        $this->assertSame(1, WiseLiquidityEvent::query()->count());
    }

    public function test_rejects_an_invalid_signature(): void
    {
        $body = '{"event_type":"transfers#state-change"}';

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            $this->headers(base64_encode('not-a-signature')),
            $body,
        )
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid Wise webhook signature.');
    }

    public function test_rejects_webhook_when_public_key_is_missing(): void
    {
        Setting::query()->where('key', 'wise.webhook_public_key')->update(['value' => '']);

        $body = '{"event_type":"transfers#state-change"}';

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            $this->headers($this->sign($body)),
            $body,
        )
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid Wise webhook signature.');
    }

    public function test_signed_webhook_is_accepted_while_the_api_is_in_maintenance(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'system.maintenance_mode'],
            ['label' => 'Modo de manutenção', 'type' => 'toggle', 'value' => 'true'],
        );

        $this->getJson('/api/wise/health')->assertStatus(503);

        Queue::fake([ProcessWiseWebhookJob::class]);

        $body = json_encode([
            'event_type' => 'transfers#state-change',
            'data' => ['resource' => ['id' => 99]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/webhooks/wise',
            [],
            [],
            [],
            $this->headers($this->sign($body)),
            $body,
        )
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function sign(string $body): string
    {
        $signature = '';
        openssl_sign($body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE_SHA256' => $signature,
        ];
    }
}

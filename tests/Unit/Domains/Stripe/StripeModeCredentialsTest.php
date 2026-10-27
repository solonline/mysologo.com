<?php

namespace Tests\Unit\Domains\Stripe;

use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

class StripeModeCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_mode_does_not_fall_back_to_the_live_secret(): void
    {
        $this->putSetting('stripe.eur.secret_key', 'sk_live_real');
        $this->putSetting('stripe.eur.test_secret_key', '');

        $client = app(StripeHttpClient::class);
        $client->forMode('test', 'eur');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stripe.eur.test_secret_key');

        $method = new ReflectionMethod($client, 'client');
        $method->invoke($client);
    }

    public function test_webhook_accepts_the_test_secret_when_the_live_secret_differs(): void
    {
        $this->putSetting('stripe.eur.webhook_secret', 'whsec_live_secret');
        $this->putSetting('stripe.eur.test_webhook_secret', 'whsec_test_secret');

        $payload = json_encode([
            'id' => 'evt_test_mode',
            'object' => 'event',
            'type' => 'ping',
        ], JSON_THROW_ON_ERROR);

        $event = app(StripeHttpClient::class)->constructWebhookEvent(
            $payload,
            $this->signature($payload, 'whsec_test_secret'),
        );

        $this->assertSame('evt_test_mode', $event->id);
    }

    public function test_webhook_accepts_the_live_secret(): void
    {
        $this->putSetting('stripe.eur.webhook_secret', 'whsec_live_secret');
        $this->putSetting('stripe.eur.test_webhook_secret', 'whsec_test_secret');

        $payload = json_encode([
            'id' => 'evt_live_mode',
            'object' => 'event',
            'type' => 'ping',
        ], JSON_THROW_ON_ERROR);

        $event = app(StripeHttpClient::class)->constructWebhookEvent(
            $payload,
            $this->signature($payload, 'whsec_live_secret'),
        );

        $this->assertSame('evt_live_mode', $event->id);
    }

    public function test_webhook_rejects_an_unknown_secret(): void
    {
        $this->putSetting('stripe.eur.webhook_secret', 'whsec_live_secret');
        $this->putSetting('stripe.eur.test_webhook_secret', 'whsec_test_secret');

        $payload = json_encode(['id' => 'evt_bad', 'object' => 'event'], JSON_THROW_ON_ERROR);

        $this->expectException(SignatureVerificationException::class);

        app(StripeHttpClient::class)->constructWebhookEvent(
            $payload,
            $this->signature($payload, 'whsec_other'),
        );
    }

    public function test_publishable_key_follows_merchant_mode(): void
    {
        $this->putSetting('stripe.eur.public_key', 'pk_live_key');
        $this->putSetting('stripe.eur.test_public_key', 'pk_test_key');

        $client = app(StripeHttpClient::class);

        $this->assertSame('pk_test_key', $client->publishableKey('test', 'EUR'));
        $this->assertSame('pk_live_key', $client->publishableKey('live', 'EUR'));
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $key, 'type' => 'secret', 'value' => $value],
        );
    }

    private function signature(string $payload, string $secret): string
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$hash;
    }
}

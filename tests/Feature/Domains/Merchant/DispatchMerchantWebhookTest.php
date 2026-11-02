<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Merchant\Jobs\DispatchMerchantWebhook;
use App\Domains\Merchant\Services\MerchantWebhookSigner;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\MerchantWebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class DispatchMerchantWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_webhook_sends_hmac_signature_headers(): void
    {
        Http::fake([
            'https://loja.test/webhook' => Http::response(['ok' => true], 200),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $rawSecret = 'whsec_'.str_repeat('ab', 32);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja HMAC',
            'slug' => 'loja-hmac-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_hmac'),
            'webhook_secret' => $rawSecret,
            'webhook_url' => 'https://loja.test/webhook',
            'mode' => 'test',
            'is_active' => true,
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 1500,
            'currency' => 'EUR',
            'external_reference' => 'ORD-HMAC',
            'status' => 'completed',
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        (new DispatchMerchantWebhook($paymentRequest))->handle(app(MerchantWebhookSigner::class));

        Http::assertSent(function ($request) use ($rawSecret, $paymentRequest) {
            $timestamp = $request->header(MerchantWebhookSigner::HEADER_TIMESTAMP)[0] ?? null;
            $signature = $request->header(MerchantWebhookSigner::HEADER_SIGNATURE)[0] ?? null;
            $deliveryId = $request->header(MerchantWebhookSigner::HEADER_DELIVERY_ID)[0] ?? null;
            $body = $request->body();

            if (! $timestamp || ! $signature || ! $deliveryId) {
                return false;
            }

            $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $rawSecret);
            $payload = json_decode($body, true);

            return hash_equals($expected, $signature)
                && ($payload['type'] ?? null) === 'payment_request.completed'
                && ($payload['data']['uuid'] ?? null) === $paymentRequest->uuid
                && ($payload['data']['external_reference'] ?? null) === 'ORD-HMAC'
                && ($payload['data']['amount'] ?? null) === 1500
                && array_key_exists('fee_total', $payload['data'] ?? [])
                && ($payload['data']['fee_total'] === null)
                && array_key_exists('net_amount', $payload['data'] ?? [])
                && ($payload['data']['net_amount'] === null)
                && array_key_exists('funds_available_at', $payload['data'] ?? [])
                && ($payload['data']['funds_available_at'] === null)
                && ($payload['data']['funds_available'] ?? null) === false;
        });

        $delivery = MerchantWebhookDelivery::query()->first();

        $this->assertNotNull($delivery);
        $this->assertSame('payment_request.completed', $delivery->event_type);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame($paymentRequest->uuid, $delivery->request_body['data']['uuid'] ?? null);
        $this->assertSame(1500, $delivery->request_body['data']['amount'] ?? null);
        $this->assertSame('ORD-HMAC', $delivery->request_body['data']['external_reference'] ?? null);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_webhook_includes_fees_and_funds_available_at_from_transaction(): void
    {
        Http::fake([
            'https://loja.test/webhook-fees' => Http::response(['ok' => true], 200),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Fees',
            'slug' => 'loja-fees-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_fees'),
            'webhook_secret' => 'whsec_'.str_repeat('aa', 32),
            'webhook_url' => 'https://loja.test/webhook-fees',
            'mode' => 'test',
            'is_active' => true,
        ]);

        $transaction = \App\Models\StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => \App\Domains\Wallet\Enums\TransactionType::MERCHANT_PAYMENT,
            'direction' => \App\Domains\Wallet\Enums\TransactionDirection::Credit,
            'stripe_payment_intent_id' => 'pi_merchant_fees_'.uniqid(),
            'amount' => 5000,
            'stripe_fee' => 120,
            'platform_fee' => 80,
            'net_amount' => 4800,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'available_on' => now()->addDays(5)->toDateString(),
            'stripe_created_at' => now(),
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'transaction_id' => $transaction->id,
            'amount' => 5000,
            'currency' => 'EUR',
            'external_reference' => 'ORD-FEES',
            'status' => 'completed',
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        (new DispatchMerchantWebhook($paymentRequest))->handle(app(MerchantWebhookSigner::class));

        Http::assertSent(function ($request) use ($transaction) {
            $payload = json_decode($request->body(), true);
            $data = $payload['data'] ?? [];

            return ($data['amount'] ?? null) === 5000
                && ($data['fee_total'] ?? null) === 200
                && ($data['net_amount'] ?? null) === 4800
                && ($data['funds_available_at'] ?? null) === $transaction->available_on->toDateString()
                && ($data['funds_available'] ?? null) === false
                && ($data['settlement_status'] ?? null) === 'pending';
        });
    }

    public function test_webhook_does_not_retry_on_permanent_client_error(): void
    {
        Http::fake([
            'https://loja.test/gone' => Http::response(['error' => 'gone'], 404),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja 404',
            'slug' => 'loja-404-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_404'),
            'webhook_secret' => 'whsec_'.str_repeat('cd', 32),
            'webhook_url' => 'https://loja.test/gone',
            'mode' => 'test',
            'is_active' => true,
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 500,
            'currency' => 'EUR',
            'status' => 'completed',
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        (new DispatchMerchantWebhook($paymentRequest))->handle(app(MerchantWebhookSigner::class));

        Http::assertSentCount(1);
    }

    public function test_webhook_retries_on_server_error(): void
    {
        Http::fake([
            'https://loja.test/down' => Http::response(['error' => 'down'], 500),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja 503',
            'slug' => 'loja-503-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_503'),
            'webhook_secret' => 'whsec_'.str_repeat('ef', 32),
            'webhook_url' => 'https://loja.test/down',
            'mode' => 'test',
            'is_active' => true,
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 500,
            'currency' => 'EUR',
            'status' => 'completed',
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        try {
            (new DispatchMerchantWebhook($paymentRequest))->handle(app(MerchantWebhookSigner::class));
            $this->fail('Era esperado HTTP 500.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 500', $exception->getMessage());
        }

        $delivery = MerchantWebhookDelivery::query()->first();

        $this->assertNotNull($delivery);
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame('payment_request.completed', $delivery->event_type);
        $this->assertSame($paymentRequest->uuid, $delivery->request_body['data']['uuid'] ?? null);
    }

    public function test_payment_request_webhook_url_overrides_merchant(): void
    {
        Http::fake([
            'https://pedido.test/hook' => Http::response(['ok' => true], 200),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Override',
            'slug' => 'loja-override-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_override'),
            'webhook_secret' => 'whsec_'.str_repeat('11', 32),
            'webhook_url' => 'https://merchant.test/hook',
            'mode' => 'test',
            'is_active' => true,
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 900,
            'currency' => 'EUR',
            'webhook_url' => 'https://pedido.test/hook',
            'status' => 'completed',
            'paid_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        (new DispatchMerchantWebhook($paymentRequest))->handle(app(MerchantWebhookSigner::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://pedido.test/hook');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://merchant.test/hook');
    }
}

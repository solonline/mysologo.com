<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerMerchantTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_customer_can_list_own_merchants(): void
    {
        $customer = $this->createActiveCustomer();
        $other = $this->createActiveCustomer();

        Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Yoop',
            'slug' => 'loja-yoop',
            'api_key' => hash('sha256', 'yoop_test_abc'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Outra Loja',
            'slug' => 'outra-loja',
            'api_key' => hash('sha256', 'yoop_test_xyz'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/merchant')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Loja Yoop')
            ->assertJsonMissingPath('data.0.api_key');
    }

    public function test_customer_can_list_payment_requests_for_own_merchants(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $other = $this->createActiveCustomer();

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Sales',
            'slug' => 'loja-sales',
            'api_key' => hash('sha256', 'yoop_test_sales'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $otherMerchant = Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Loja Outra',
            'slug' => 'loja-outra-sales',
            'api_key' => hash('sha256', 'yoop_test_sales_other'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        \App\Models\MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 1200,
            'currency' => 'EUR',
            'description' => 'Minha venda',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        \App\Models\MerchantPaymentRequest::query()->create([
            'merchant_id' => $otherMerchant->id,
            'amount' => 9900,
            'currency' => 'EUR',
            'description' => 'Venda alheia',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/merchant/payment-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 1200)
            ->assertJsonPath('data.0.description', 'Minha venda')
            ->assertJsonPath('data.0.settlement_status', null);
    }

    public function test_customer_can_create_payment_request_for_own_merchant(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Yoop',
            'slug' => 'loja-yoop-pr',
            'api_key' => hash('sha256', 'yoop_test_pr'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/payment-requests', [
                'amount' => 2500,
                'description' => 'Pedido teste',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 2500)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['uuid', 'url', 'expires_at']]);

        $paymentRequest = \App\Models\MerchantPaymentRequest::query()
            ->where('merchant_id', $merchant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($paymentRequest);
        $this->assertTrue(
            $paymentRequest->expires_at->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()),
            'Sem expires_in, expires_at deve ser ~1 ano.',
        );
    }

    public function test_customer_can_create_payment_request_with_explicit_payment_method_types(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Yoop',
            'slug' => 'loja-yoop-pm',
            'api_key' => hash('sha256', 'yoop_test_pm'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/payment-requests', [
                'amount' => 1800,
                'payment_method_types' => ['card', 'mb_way'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method_types', ['card', 'mb_way']);

        $this->assertDatabaseHas('merchant_payment_requests', [
            'merchant_id' => $merchant->id,
            'amount' => 1800,
        ]);
    }

    public function test_customer_cannot_create_payment_request_for_other_merchant(): void
    {
        $customer = $this->createActiveCustomer();
        $other = $this->createActiveCustomer();
        $merchant = Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Loja Alheia',
            'slug' => 'loja-alheia',
            'api_key' => hash('sha256', 'yoop_test_other'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/payment-requests', [
                'amount' => 1000,
            ])
            ->assertNotFound();
    }

    public function test_customer_can_view_and_rotate_merchant_credentials(): void
    {
        $customer = $this->createActiveCustomer();
        $secret = 'whsec_'.str_repeat('11', 32);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Creds',
            'slug' => 'loja-creds',
            'api_key' => hash('sha256', 'yoop_test_old_key'),
            'webhook_secret' => $secret,
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/merchant/'.$merchant->id.'/credentials')
            ->assertOk()
            ->assertJsonPath('api_key_retrievable', false)
            ->assertJsonPath('webhook_secret', $secret)
            ->assertJsonMissingPath('api_key');

        $rotateKey = $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/credentials/rotate', [
                'api_key' => true,
            ])
            ->assertOk()
            ->assertJsonStructure(['api_key', 'data' => ['id', 'name']])
            ->assertJsonMissingPath('webhook_secret');

        $newApiKey = $rotateKey->json('api_key');
        $this->assertNotSame('yoop_test_old_key', $newApiKey);
        $this->assertStringStartsWith('yoop_test_', $newApiKey);

        $merchant->refresh();
        $this->assertSame(hash('sha256', $newApiKey), $merchant->api_key);
        $this->assertSame($secret, $merchant->webhook_secret);

        $rotateSecret = $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/credentials/rotate', [
                'webhook_secret' => true,
            ])
            ->assertOk()
            ->assertJsonStructure(['webhook_secret', 'data' => ['id', 'name']])
            ->assertJsonMissingPath('api_key');

        $newWebhookSecret = $rotateSecret->json('webhook_secret');
        $this->assertNotSame($secret, $newWebhookSecret);
        $this->assertStringStartsWith('whsec_', $newWebhookSecret);

        $merchant->refresh();
        $this->assertSame($newWebhookSecret, $merchant->webhook_secret);

        $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/credentials/rotate', [])
            ->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/merchant/'.$merchant->id.'/credentials/rotate', [
                'api_key' => true,
                'webhook_secret' => true,
            ])
            ->assertUnprocessable();
    }

    public function test_customer_can_update_own_merchant(): void
    {
        $customer = $this->createActiveCustomer();
        $other = $this->createActiveCustomer();

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Antiga',
            'slug' => 'loja-antiga',
            'api_key' => hash('sha256', 'yoop_test_update'),
            'webhook_url' => 'https://old.test/hook',
            'mode' => 'test',
            'is_active' => true,
        ]);

        Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Loja Alheia',
            'slug' => 'loja-alheia-slug',
            'api_key' => hash('sha256', 'yoop_test_other_slug'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$merchant->id, [
                'name' => 'Loja Nova',
                'slug' => 'loja-nova',
                'webhook_url' => 'https://nova.test/webhook',
                'mode' => 'live',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Loja Nova')
            ->assertJsonPath('data.slug', 'loja-nova')
            ->assertJsonPath('data.webhook_url', 'https://nova.test/webhook')
            ->assertJsonPath('data.mode', 'live');

        $merchant->refresh();
        $this->assertSame('Loja Nova', $merchant->name);
        $this->assertSame('loja-nova', $merchant->slug);
        $this->assertSame('https://nova.test/webhook', $merchant->webhook_url);
        $this->assertSame('live', $merchant->mode);

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$merchant->id, [
                'slug' => 'loja-alheia-slug',
            ])
            ->assertUnprocessable();

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$merchant->id, [])
            ->assertUnprocessable();
    }

    public function test_customer_cannot_update_other_merchant(): void
    {
        $customer = $this->createActiveCustomer();
        $other = $this->createActiveCustomer();

        $merchant = Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Loja Alheia',
            'slug' => 'loja-alheia-edit',
            'api_key' => hash('sha256', 'yoop_test_edit_other'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$merchant->id, [
                'name' => 'Hack',
            ])
            ->assertNotFound();
    }

    /** @param array<string, mixed> $attributes */
    private function createActiveCustomer(array $attributes = [])
    {
        return $this->createRetailCustomer(array_merge([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ], $attributes));
    }
}

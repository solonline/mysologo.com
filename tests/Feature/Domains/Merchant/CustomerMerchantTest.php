<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Merchant\Mail\SecurePayWelcomeMail;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerMerchantTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_register_sends_securepay_welcome_email(): void
    {
        Mail::fake();

        $customer = $this->createActiveCustomer([
            'name' => 'Ana Silva',
            'email' => 'ana@loja.test',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Loja da Ana',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Loja da Ana');

        Mail::assertSent(SecurePayWelcomeMail::class, function (SecurePayWelcomeMail $mail) use ($customer): bool {
            $html = $mail->render();

            return $mail->hasTo($customer->email)
                && $mail->envelope()->subject === 'Bem-vindo à SecurePay'
                && $mail->merchant->name === 'Loja da Ana'
                && str_contains($html, 'Loja da Ana')
                && str_contains($html, 'data:image/png;base64,');
        });
    }

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

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 1200,
            'currency' => 'EUR',
            'description' => 'Minha venda',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        MerchantPaymentRequest::query()->create([
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

        $paymentRequest = MerchantPaymentRequest::query()
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

    public function test_customer_can_have_one_test_merchant_and_many_live(): void
    {
        Mail::fake();

        $customer = $this->createActiveCustomer();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Loja Teste',
                'mode' => 'test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'test');

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Loja Teste 2',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Loja Live 1',
                'mode' => 'live',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'live');

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Loja Live 2',
                'mode' => 'live',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'live');

        $this->assertSame(1, Merchant::query()->where('customer_id', $customer->id)->where('mode', 'test')->count());
        $this->assertSame(2, Merchant::query()->where('customer_id', $customer->id)->where('mode', 'live')->count());
    }

    public function test_customer_cannot_change_merchant_mode(): void
    {
        $customer = $this->createActiveCustomer();

        $test = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Teste',
            'slug' => 'loja-teste-limite',
            'api_key' => hash('sha256', 'yoop_test_limit'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $live = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Live',
            'slug' => 'loja-live-limite',
            'api_key' => hash('sha256', 'yoop_live_limit'),
            'mode' => 'live',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$live->id, [
                'name' => 'Loja Live Nova',
                'mode' => 'test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$test->id, [
                'mode' => 'live',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');

        $live->refresh();
        $test->refresh();
        $this->assertSame('live', $live->mode);
        $this->assertSame('Loja Live', $live->name);
        $this->assertSame('test', $test->mode);
    }

    public function test_customer_can_delete_own_merchant_and_register_test_again(): void
    {
        Mail::fake();

        $customer = $this->createActiveCustomer();
        $other = $this->createActiveCustomer();

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja a excluir',
            'slug' => 'loja-a-excluir',
            'api_key' => hash('sha256', 'yoop_test_delete'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $foreign = Merchant::query()->create([
            'customer_id' => $other->id,
            'name' => 'Loja alheia',
            'slug' => 'loja-alheia-delete',
            'api_key' => hash('sha256', 'yoop_test_delete_other'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/merchant/'.$foreign->id)
            ->assertNotFound();

        $this->withToken($token)
            ->deleteJson('/api/merchant/'.$merchant->id)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSoftDeleted('merchants', ['id' => $merchant->id]);

        $this->withToken($token)
            ->getJson('/api/merchant')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($token)
            ->patchJson('/api/merchant/'.$merchant->id, [
                'name' => 'Já excluída',
            ])
            ->assertNotFound();

        $this->withToken($token)
            ->postJson('/api/merchant/register', [
                'name' => 'Nova loja teste',
                'mode' => 'test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'test');
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
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Loja Nova')
            ->assertJsonPath('data.slug', 'loja-nova')
            ->assertJsonPath('data.webhook_url', 'https://nova.test/webhook')
            ->assertJsonPath('data.mode', 'test');

        $merchant->refresh();
        $this->assertSame('Loja Nova', $merchant->name);
        $this->assertSame('loja-nova', $merchant->slug);
        $this->assertSame('https://nova.test/webhook', $merchant->webhook_url);
        $this->assertSame('test', $merchant->mode);

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

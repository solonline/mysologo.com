<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class MerchantRateLimitTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_payment_request_limiter_is_120_per_minute_keyed_by_merchant(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $merchantA = $this->makeMerchant($customer, 'limit-a', 'yoop_test_limit_a');
        $merchantB = $this->makeMerchant($customer, 'limit-b', 'yoop_test_limit_b');

        $limiter = RateLimiter::limiter('merchant-payment-requests');
        $limitA = $limiter($this->merchantRequest($merchantA));
        $limitB = $limiter($this->merchantRequest($merchantB));

        $this->assertInstanceOf(Limit::class, $limitA);
        $this->assertInstanceOf(Limit::class, $limitB);
        $this->assertSame(120, $limitA->maxAttempts);
        $this->assertNotSame($limitA->key, $limitB->key);
        $this->assertStringContainsString((string) $merchantA->id, $limitA->key);
        $this->assertStringContainsString((string) $merchantB->id, $limitB->key);
    }

    public function test_terminal_limiters_are_60_per_minute_and_use_separate_buckets(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $merchant = $this->makeMerchant($customer, 'limit-tpa', 'yoop_test_limit_tpa');
        $request = $this->merchantRequest($merchant);

        $token = RateLimiter::limiter('merchant-terminal-token')($request);
        $intent = RateLimiter::limiter('merchant-terminal-intent')($request);

        $this->assertInstanceOf(Limit::class, $token);
        $this->assertInstanceOf(Limit::class, $intent);
        $this->assertSame(60, $token->maxAttempts);
        $this->assertSame(60, $intent->maxAttempts);
        $this->assertNotSame($token->key, $intent->key);
    }

    public function test_merchant_api_returns_429_after_payment_request_limit(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $rawKey = 'yoop_test_rl_429';
        $merchant = $this->makeMerchant($customer, 'limit-429', $rawKey);

        $limit = RateLimiter::limiter('merchant-payment-requests')(
            $this->merchantRequest($merchant),
        );

        $this->assertInstanceOf(Limit::class, $limit);

        $throttleKey = md5('merchant-payment-requests'.$limit->key);

        for ($i = 0; $i < $limit->maxAttempts; $i++) {
            RateLimiter::hit($throttleKey, $limit->decaySeconds);
        }

        $this->withHeaders(['X-Merchant-Key' => $rawKey])
            ->postJson('/api/merchant/payment-requests', ['amount' => 100])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Tente novamente em breve.');
    }

    public function test_second_merchant_can_create_after_first_hits_limit(): void
    {
        $customer = $this->createActiveCustomer(['currency' => 'EUR']);
        $rawA = 'yoop_test_rl_a';
        $rawB = 'yoop_test_rl_b';
        $merchantA = $this->makeMerchant($customer, 'limit-iso-a', $rawA);
        $this->makeMerchant($customer, 'limit-iso-b', $rawB);

        $limitA = RateLimiter::limiter('merchant-payment-requests')(
            $this->merchantRequest($merchantA),
        );

        $this->assertInstanceOf(Limit::class, $limitA);

        $throttleKey = md5('merchant-payment-requests'.$limitA->key);

        for ($i = 0; $i < $limitA->maxAttempts; $i++) {
            RateLimiter::hit($throttleKey, $limitA->decaySeconds);
        }

        $this->withHeaders(['X-Merchant-Key' => $rawB])
            ->postJson('/api/merchant/payment-requests', ['amount' => 150])
            ->assertCreated()
            ->assertJsonPath('data.amount', 150);

        $this->withHeaders(['X-Merchant-Key' => $rawA])
            ->postJson('/api/merchant/payment-requests', ['amount' => 150])
            ->assertStatus(429);
    }

    private function makeMerchant(Customer $customer, string $slug, string $rawKey): Merchant
    {
        return Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja '.$slug,
            'slug' => $slug,
            'api_key' => hash('sha256', $rawKey),
            'mode' => 'test',
            'is_active' => true,
        ]);
    }

    private function merchantRequest(Merchant $merchant): Request
    {
        $request = Request::create('/api/merchant/payment-requests', 'POST');
        $request->attributes->set('merchant', $merchant);

        return $request;
    }

    /** @param array<string, mixed> $attributes */
    private function createActiveCustomer(array $attributes = []): Customer
    {
        return $this->createRetailCustomer(array_merge([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ], $attributes));
    }
}

<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Setting;
use App\Models\StripePaymentIntent;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripeTopupDailyLimitTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    private StripePaymentMethodFee $feeConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feeConfig = StripePaymentMethodFee::query()->create([
            'payment_method' => 'card',
            'label' => 'Cartão',
            'stripe_fee_percentage' => 0.0150,
            'stripe_fee_fixed' => 0.25,
            'platform_fee_percentage' => 0.0050,
            'platform_fee_fixed' => 0.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_topup_payment_intent_is_stored_without_financial_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        $service = app(StripeService::class);

        $result = $service->createPaymentIntent(
            customer: $customer,
            amount: 5_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $this->assertSame(TransactionType::TOPUP, $result['payment_intent']->type);
        $this->assertSame('card', $result['payment_intent']->payment_method_type);

        $this->assertDatabaseHas('stripe_payment_intents', [
            'id' => $result['payment_intent']->id,
            'type' => TransactionType::TOPUP->value,
            'payment_method_type' => 'card',
            'status' => StripePaymentIntentStatus::RequiresPaymentMethod->value,
            'purpose' => StripePaymentIntentPurpose::Topup->value,
        ]);

        $this->assertDatabaseCount('stripe_transactions', 0);
    }

    public function test_topup_allows_any_amount_even_when_daily_immediate_quota_is_exhausted(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'stripe_payment_method_fee_id' => $this->feeConfig->id,
            'type' => TransactionType::TOPUP,
            'direction' => TransactionDirection::Credit,
            'payment_method_type' => 'card',
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 10_000,
            'immediate_gross_amount' => 10_000,
            'wallester_funding_net_amount' => 10_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $service = app(StripeService::class);

        $result = $service->createPaymentIntent(
            customer: $customer,
            amount: 20_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $this->assertSame(TransactionType::TOPUP, $result['payment_intent']->type);
        $this->assertSame(20_000, (int) $result['payment_intent']->amount);
    }

    public function test_topup_daily_limit_ignores_open_payment_intents(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        StripePaymentIntent::query()->create([
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => 'pi_open_legacy',
            'stripe_payment_method_fee_id' => $this->feeConfig->id,
            'type' => TransactionType::TOPUP,
            'purpose' => StripePaymentIntentPurpose::Topup,
            'amount' => 9_000,
            'currency' => 'EUR',
            'payment_method_type' => 'card',
            'status' => StripePaymentIntentStatus::RequiresPaymentMethod,
        ]);

        $service = app(StripeService::class);

        $result = $service->createPaymentIntent(
            customer: $customer,
            amount: 5_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $this->assertSame(TransactionType::TOPUP, $result['payment_intent']->type);
    }

    public function test_create_intent_payment_endpoint_registers_topup_type(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        Setting::query()->updateOrCreate(
            ['key' => 'stripe.eur.public_key'],
            ['label' => 'Produção — Chave pública (pk_)', 'type' => 'text', 'value' => 'pk_live_topup_account'],
        );

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/stripe/create-intent-payment', [
                'amount' => 5_000,
                'payment_method_id' => $this->feeConfig->id,
                'currency' => 'eur',
            ], [
                'Idempotency-Key' => 'topup-test-'.uniqid(),
            ])
            ->assertCreated()
            ->assertJsonPath('payment_intent.type', TransactionType::TOPUP->value)
            ->assertJsonPath('payment_intent.payment_method_type', 'card')
            ->assertJsonPath('publishable_key', 'pk_live_topup_account')
            ->assertJsonMissing(['transaction' => []]);
    }

    private function createActiveCustomer()
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }

    private function mockStripeGateway(): void
    {
        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('createPaymentIntent')
            ->andReturnUsing(function () {
                static $counter = 0;
                $counter++;

                return [
                    'status' => 'success',
                    'data' => [
                        'id' => 'pi_test_'.$counter,
                        'client_secret' => 'cs_test_'.$counter,
                        'status' => 'requires_payment_method',
                        'created' => now()->timestamp,
                    ],
                ];
            });

        $this->app->instance(StripeGatewayInterface::class, $gateway);
    }
}

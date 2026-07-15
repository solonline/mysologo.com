<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripePaymentIntent;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripePaymentIntentLifecycleTest extends TestCase
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

    public function test_webhook_succeeded_creates_financial_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        $service = app(StripeService::class);
        $created = $service->createPaymentIntent(
            customer: $customer,
            amount: 5_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $this->assertDatabaseCount('stripe_transactions', 0);

        $service->handleWebhook([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $created['payment_intent']->stripe_payment_intent_id,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        $this->assertDatabaseHas('stripe_transactions', [
            'stripe_payment_intent_id' => $created['payment_intent']->stripe_payment_intent_id,
            'status' => 'succeeded',
            'amount' => 5_000,
        ]);

        $paymentIntent = StripePaymentIntent::findOrFail($created['payment_intent']->id);
        $this->assertNotNull($paymentIntent->stripe_transaction_id);
        $this->assertSame(StripePaymentIntentStatus::Succeeded, $paymentIntent->status);
    }

    public function test_show_payment_intent_endpoint_returns_linked_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        $service = app(StripeService::class);
        $created = $service->createPaymentIntent(
            customer: $customer,
            amount: 5_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $service->handleWebhook([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $created['payment_intent']->stripe_payment_intent_id,
                    'status' => 'succeeded',
                ],
            ],
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/stripe/payment-intents/'.$created['payment_intent']->id)
            ->assertOk()
            ->assertJsonPath('stripe_transaction_id', StripeTransaction::first()->id)
            ->assertJsonPath('transaction.status', 'succeeded');
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
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'pi_lifecycle_1',
                    'client_secret' => 'cs_lifecycle_1',
                    'status' => 'requires_payment_method',
                    'created' => now()->timestamp,
                ],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);
    }
}

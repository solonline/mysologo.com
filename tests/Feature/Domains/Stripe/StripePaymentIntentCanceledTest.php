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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripePaymentIntentCanceledTest extends TestCase
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

    public function test_canceled_webhook_marks_payment_intent_without_creating_transaction(): void
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
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => $created['payment_intent']->stripe_payment_intent_id,
                    'status' => 'canceled',
                ],
            ],
        ]);

        $paymentIntent = StripePaymentIntent::findOrFail($created['payment_intent']->id);
        $this->assertSame(StripePaymentIntentStatus::Canceled, $paymentIntent->status);
        $this->assertNotNull($paymentIntent->canceled_at);
        $this->assertDatabaseCount('stripe_transactions', 0);
    }

    public function test_abandoned_open_payment_intent_does_not_appear_as_wallet_transaction(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->mockStripeGateway();

        app(StripeService::class)->createPaymentIntent(
            customer: $customer,
            amount: 5_000,
            paymentMethodFeeId: $this->feeConfig->id,
            isTopup: true,
        );

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
                    'id' => 'pi_canceled_1',
                    'client_secret' => 'cs_canceled_1',
                    'status' => 'requires_payment_method',
                    'created' => now()->timestamp,
                ],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);
    }
}

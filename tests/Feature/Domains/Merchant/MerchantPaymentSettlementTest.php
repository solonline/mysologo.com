<?php

namespace Tests\Feature\Domains\Merchant;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Repositories\TransactionRepository;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerPaymentMethodFee;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\StripePaymentIntent;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class MerchantPaymentSettlementTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallesterSettings();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_charge_updated_credits_merchant_payment_as_pending_without_wallester_job(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createPlatformCustomer();
        $this->createStripeAccount($customer);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_merchant_customer');

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Settlement',
            'slug' => 'loja-settlement-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_settlement'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_merchant_1',
        ]);

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 5_000,
            'currency' => 'EUR',
            'description' => 'Venda teste',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_merchant_1',
            'transaction_id' => $transaction->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_merchant_1',
                    'payment_intent' => 'pi_merchant_1',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_merchant_1',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);

        $transaction->refresh();

        $this->assertNull($transaction->balance_settled_at);
        $this->assertSame(PartnerFundingStatus::AwaitingStripePayout, $transaction->partner_funding_status);
        $this->assertSame(0, (int) $transaction->wallester_funding_net_amount);
        $this->assertSame(0, (int) $transaction->immediate_gross_amount);
        $this->assertGreaterThan(0, (int) $transaction->net_amount);
        $this->assertSame((int) $transaction->net_amount, (int) $transaction->stripe_deferred_net_amount);

        $this->assertSame(25, (int) $transaction->platform_fee);
        $this->assertSame(100, (int) $transaction->stripe_fee);
        $this->assertSame(4_875, (int) $transaction->net_amount);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(0, (int) $balance->available);
        $this->assertSame((int) $transaction->net_amount, (int) $balance->pending);

        $this->assertTrue(
            LedgerEntry::query()
                ->where('customer_id', $customer->id)
                ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
                ->where('amount', $transaction->net_amount)
                ->exists()
        );

        $recent = app(TransactionRepository::class)->getRecent($customer);
        $this->assertFalse(
            $recent->contains(fn (StripeTransaction $row) => $row->id === $transaction->id),
            'merchant_payment não deve aparecer em movimentos recentes',
        );
    }

    public function test_charge_updated_uses_customer_merchant_fee_when_configured(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createPlatformCustomer();
        $this->createStripeAccount($customer);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_merchant_fee');

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Taxa Custom',
            'slug' => 'loja-taxa-custom-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_merchant_fee'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        CustomerPaymentMethodFee::query()->create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'tpa_id' => null,
            'payment_method' => 'all',
            'label' => 'Taxa merchant EUR',
            'fee_percentage' => 0.0200,
            'fee_fixed' => 0.10,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_merchant_custom_fee',
        ]);

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 5_000,
            'currency' => 'EUR',
            'description' => 'Venda taxa custom',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_merchant_custom_fee',
            'transaction_id' => $transaction->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_merchant_custom_fee',
                    'payment_intent' => 'pi_merchant_custom_fee',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_merchant_custom_fee',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        $transaction->refresh();

        // Taxa personalizada 2% + 0.10€ = 110; stripe 100; platform = 110 - 100
        $this->assertSame(10, (int) $transaction->platform_fee);
        $this->assertSame(100, (int) $transaction->stripe_fee);
        $this->assertSame(4_890, (int) $transaction->net_amount);
    }

    public function test_charge_updated_embeds_stripe_fee_inside_custom_platform_fee(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createPlatformCustomer();
        $this->createStripeAccount($customer);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Taxa 10',
            'slug' => 'loja-taxa-10-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_merchant_fee_10'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        CustomerPaymentMethodFee::query()->create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'tpa_id' => null,
            'payment_method' => 'all',
            'label' => 'Taxa 10%',
            'fee_percentage' => 0.1000,
            'fee_fixed' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_merchant_fee_embed',
        ]);

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 10_000,
            'currency' => 'EUR',
            'description' => 'Venda 100',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_merchant_fee_embed',
            'transaction_id' => $transaction->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_merchant_fee_embed',
                    'payment_intent' => 'pi_merchant_fee_embed',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_merchant_fee_embed',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        $transaction->refresh();

        // 100€, Stripe 1.00€, taxa personalizada 10% = 10.00€ → stripe_fee 100, platform_fee 900
        $this->assertSame(100, (int) $transaction->stripe_fee);
        $this->assertSame(900, (int) $transaction->platform_fee);
        $this->assertSame(9_000, (int) $transaction->net_amount);
    }

    public function test_charge_updated_falls_back_to_global_fee_when_custom_does_not_cover_stripe(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createPlatformCustomer();
        $this->createStripeAccount($customer);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Taxa Baixa',
            'slug' => 'loja-taxa-baixa-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_merchant_fee_low'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        CustomerPaymentMethodFee::query()->create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'tpa_id' => null,
            'payment_method' => 'all',
            'label' => 'Taxa insuficiente',
            'fee_percentage' => 0.0010,
            'fee_fixed' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_merchant_fee_low',
        ]);

        MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 5_000,
            'currency' => 'EUR',
            'description' => 'Venda taxa baixa',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_merchant_fee_low',
            'transaction_id' => $transaction->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_merchant_fee_low',
                    'payment_intent' => 'pi_merchant_fee_low',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_merchant_fee_low',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        $transaction->refresh();

        // Custom 0.1% de 50€ = 5 < Stripe 100 → config global 0.5% = 25
        $this->assertSame(100, (int) $transaction->stripe_fee);
        $this->assertSame(25, (int) $transaction->platform_fee);
        $this->assertSame(4_875, (int) $transaction->net_amount);
    }

    public function test_payment_intent_succeeded_completes_merchant_request_with_pending_settlement(): void
    {
        Bus::fake();

        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);

        $merchant = Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Loja Complete',
            'slug' => 'loja-complete-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_complete'),
            'mode' => 'test',
            'is_active' => true,
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_500,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_500,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_merchant_complete',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
        ]);

        $paymentRequest = MerchantPaymentRequest::query()->create([
            'merchant_id' => $merchant->id,
            'amount' => 2_500,
            'currency' => 'EUR',
            'description' => 'Pedido complete',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_merchant_complete',
            'expires_at' => now()->addDay(),
        ]);

        StripePaymentIntent::query()->create([
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => 'pi_merchant_complete',
            'stripe_transaction_id' => $transaction->id,
            'amount' => 2_500,
            'currency' => 'EUR',
            'status' => 'requires_payment_method',
            'type' => TransactionType::MERCHANT_PAYMENT->value,
            'purpose' => 'merchant_payment',
        ]);

        app(StripeService::class)->handleWebhook([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_merchant_complete',
                    'status' => 'succeeded',
                    'amount' => 2_500,
                    'currency' => 'eur',
                    'metadata' => [
                        'merchant_payment_request_id' => (string) $paymentRequest->id,
                        'purpose' => 'merchant_payment',
                    ],
                ],
            ],
        ]);

        $paymentRequest->refresh();

        $this->assertSame('completed', $paymentRequest->status);
        $this->assertNotNull($paymentRequest->paid_at);
        $this->assertSame($transaction->id, $paymentRequest->transaction_id);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/merchant/payment-requests')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.settlement_status', 'pending');
    }

    private function createActiveCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ]);
    }

    private function createPlatformCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'email' => 'master-merchant@yoop.com',
            'username' => 'platform_merchant',
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }

    private function mockStripeGatewayForChargeUpdated(): void
    {
        StripePaymentMethodFee::query()->create([
            'payment_method' => 'card',
            'label' => 'Cartão',
            'stripe_fee_percentage' => 0.0150,
            'stripe_fee_fixed' => 0.25,
            'platform_fee_percentage' => 0.0050,
            'platform_fee_fixed' => 0.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('getBalanceTransaction')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'fee' => 100,
                    'available_on' => now()->addDays(2)->timestamp,
                ],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);
    }
}

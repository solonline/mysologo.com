<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripeCashBalanceFundingTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_funded_cash_balance_webhook_reconciles_via_payment_intent(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_cash_balance_1');

        $this->assertDatabaseHas('customer_stripe_accounts', [
            'stripe_customer_id' => 'cus_cash_balance_1',
        ]);

        StripePaymentMethodFee::query()->create([
            'payment_method' => 'bank_transfer',
            'label' => 'Transferência Bancária',
            'stripe_fee_percentage' => 0,
            'stripe_fee_fixed' => 0,
            'platform_fee_percentage' => 0,
            'platform_fee_fixed' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->app->forgetInstance(StripeService::class);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['amount'] === 100
                    && $payload['currency'] === 'eur'
                    && $payload['customer'] === 'cus_cash_balance_1'
                    && $payload['payment_method_types'] === ['customer_balance']
                    && ($payload['payment_method_data']['type'] ?? null) === 'customer_balance'
                    && ($payload['confirm'] ?? false) === true
                    && ($payload['metadata']['purpose'] ?? null) === 'bank_transfer_topup';
            }))
            ->andReturn([
                'status' => 'success',
                'data' => ['id' => 'pi_bank_topup_1', 'status' => 'succeeded', 'client_secret' => 'secret'],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);

        $service = app(StripeService::class);
        $service->handleWebhook([
            'type' => 'customer_cash_balance_transaction.created',
            'data' => ['object' => $this->sampleFundedCashBalancePayload('cus_cash_balance_1')],
        ]);

        $this->assertDatabaseHas('stripe_payment_intents', [
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => 'pi_bank_topup_1',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_test_1',
        ]);

        $this->assertDatabaseHas('stripe_transactions', [
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => 'pi_bank_topup_1',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_test_1',
            'type' => TransactionType::BANK_TRANSFER->value,
            'payment_method_type' => 'bank_transfer',
            'amount' => 100,
            'status' => 'succeeded',
            'bank_transfer_sender_name' => 'Test Sender',
        ]);
    }

    public function test_funded_cash_balance_webhook_is_idempotent(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_cash_balance_2');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'direction' => 'credit',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_test_1',
            'payment_method_type' => 'bank_transfer',
            'amount' => 100,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $this->app->forgetInstance(StripeService::class);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        app(StripeService::class)->handleWebhook([
            'type' => 'customer_cash_balance_transaction.created',
            'data' => ['object' => $this->sampleFundedCashBalancePayload('cus_cash_balance_2')],
        ]);

        $this->assertSame(1, StripeTransaction::query()->count());
    }

    public function test_non_funded_cash_balance_transactions_are_ignored(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_cash_balance_3');

        $this->app->forgetInstance(StripeService::class);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        app(StripeService::class)->handleWebhook([
            'type' => 'customer_cash_balance_transaction.created',
            'data' => [
                'object' => array_merge($this->sampleFundedCashBalancePayload('cus_cash_balance_3'), [
                    'type' => 'applied_to_payment',
                ]),
            ],
        ]);

        $this->assertDatabaseCount('stripe_transactions', 0);
    }

    public function test_charge_updated_after_cash_balance_reconciliation_waits_for_stripe_payout(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_cash_balance_4');
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_bank_topup');

        $feeConfig = StripePaymentMethodFee::query()->create([
            'payment_method' => 'bank_transfer',
            'label' => 'Transferência Bancária',
            'stripe_fee_percentage' => 0,
            'stripe_fee_fixed' => 0,
            'platform_fee_percentage' => 0.005,
            'platform_fee_fixed' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $amount = 5_000;
        $stripeFeeFromBalance = 3;

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'direction' => 'credit',
            'stripe_payment_intent_id' => 'pi_bank_topup_2',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_test_2',
            'payment_method_type' => 'bank_transfer',
            'amount' => $amount,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $this->app->forgetInstance(StripeService::class);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('getBalanceTransaction')
            ->once()
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'fee' => $stripeFeeFromBalance,
                    'available_on' => now()->addDays(2)->timestamp,
                ],
            ]);
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        $transaction = StripeTransaction::query()->firstOrFail();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_bank_topup_1',
                    'payment_intent' => 'pi_bank_topup_2',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_bank_topup_1',
                    'payment_method_details' => ['type' => 'customer_balance'],
                ],
            ],
        ]);

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);

        $expectedPlatformFee = $feeConfig->fresh()->calculatePlatformFee($amount);
        $expectedNetAmount = $amount - $stripeFeeFromBalance - $expectedPlatformFee;

        $transaction->refresh();
        $this->assertSame('bank_transfer', $transaction->payment_method_type);
        $this->assertSame($stripeFeeFromBalance, (int) $transaction->stripe_fee);
        $this->assertSame($expectedPlatformFee, (int) $transaction->platform_fee);
        $this->assertSame($expectedNetAmount, (int) $transaction->net_amount);
        $this->assertSame(0, (int) $transaction->immediate_gross_amount);
        $this->assertSame(0, (int) $transaction->wallester_funding_net_amount);
        $this->assertSame($expectedNetAmount, (int) $transaction->stripe_deferred_net_amount);
        $this->assertNull($transaction->balance_settled_at);
        $this->assertSame($feeConfig->id, (int) $transaction->stripe_payment_method_fee_id);
        $this->assertSame($expectedNetAmount, (int) \App\Models\CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->value('pending'));
        $this->assertSame(0, (int) \App\Models\CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->value('wallester_allocated'));
    }

    public function test_funds_available_reconciles_funded_cash_balance_without_a_second_payment_intent(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_funds_available');

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('listCashBalanceTransactions')
            ->twice()
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'data' => [$this->sampleFundedCashBalancePayload('cus_funds_available')],
                ],
            ]);
        $gateway->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'pi_funds_available',
                    'status' => 'succeeded',
                ],
            ]);
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        $payload = [
            'type' => 'cash_balance.funds_available',
            'data' => [
                'object' => [
                    'object' => 'cash_balance',
                    'customer' => 'cus_funds_available',
                    'available' => ['eur' => 100],
                ],
            ],
        ];

        app(StripeService::class)->handleWebhook($payload);
        app(StripeService::class)->handleWebhook($payload);

        $this->assertSame(1, StripeTransaction::query()->count());
        $this->assertSame(TransactionType::BANK_TRANSFER, StripeTransaction::query()->first()->type);
    }

    public function test_funded_webhook_stores_available_on_from_the_charge_balance_transaction(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_available_on');

        $availableOn = now()->addDays(5)->startOfDay();

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'pi_available_on',
                    'status' => 'succeeded',
                    'latest_charge' => 'ch_available_on',
                ],
            ]);
        $gateway->shouldReceive('getCharge')
            ->once()
            ->with('ch_available_on', 'eur')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'ch_available_on',
                    'balance_transaction' => 'txn_available_on',
                ],
            ]);
        $gateway->shouldReceive('getBalanceTransaction')
            ->once()
            ->with('txn_available_on')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'txn_available_on',
                    'available_on' => $availableOn->timestamp,
                ],
            ]);
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        app(StripeService::class)->handleWebhook([
            'type' => 'customer_cash_balance_transaction.created',
            'data' => [
                'object' => $this->sampleFundedCashBalancePayload('cus_available_on'),
            ],
        ]);

        $transaction = StripeTransaction::query()->first();

        $this->assertSame('ch_available_on', $transaction->stripe_charge_id);
        $this->assertSame($availableOn->toDateString(), $transaction->available_on->toDateString());
    }

    public function test_funded_webhook_stores_available_on_from_a_py_charge(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
        ]);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_available_on_py');

        $availableOn = now()->addDays(5)->startOfDay();

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'pi_available_on_py',
                    'status' => 'succeeded',
                    'latest_charge' => 'py_available_on',
                ],
            ]);
        $gateway->shouldReceive('getCharge')
            ->once()
            ->with('py_available_on', 'eur')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'py_available_on',
                    'balance_transaction' => 'txn_available_on_py',
                ],
            ]);
        $gateway->shouldReceive('getBalanceTransaction')
            ->once()
            ->with('txn_available_on_py')
            ->andReturn([
                'status' => 'success',
                'data' => [
                    'id' => 'txn_available_on_py',
                    'available_on' => $availableOn->timestamp,
                ],
            ]);
        $this->app->instance(StripeGatewayInterface::class, $gateway);

        app(StripeService::class)->handleWebhook([
            'type' => 'customer_cash_balance_transaction.created',
            'data' => [
                'object' => $this->sampleFundedCashBalancePayload('cus_available_on_py'),
            ],
        ]);

        $transaction = StripeTransaction::query()->first();

        $this->assertSame(TransactionType::BANK_TRANSFER, $transaction->type);
        $this->assertSame('py_available_on', $transaction->stripe_charge_id);
        $this->assertSame($availableOn->toDateString(), $transaction->available_on->toDateString());
        $this->assertNull($transaction->balance_settled_at);
        $this->assertSame(0, (int) $transaction->wallester_funding_net_amount);
    }

    /** @return array<string, mixed> */
    private function sampleFundedCashBalancePayload(string $stripeCustomerId): array
    {
        return [
            'id' => 'ccsbtxn_test_1',
            'object' => 'customer_cash_balance_transaction',
            'created' => now()->timestamp,
            'currency' => 'eur',
            'customer' => $stripeCustomerId,
            'ending_balance' => 100,
            'funded' => [
                'bank_transfer' => [
                    'eu_bank_transfer' => [
                        'bic' => 'COBADEFFXXX',
                        'iban_last4' => '3000',
                        'sender_name' => 'Test Sender',
                    ],
                    'reference' => 'oi',
                    'type' => 'eu_bank_transfer',
                ],
            ],
            'livemode' => true,
            'net_amount' => 100,
            'type' => 'funded',
        ];
    }
}

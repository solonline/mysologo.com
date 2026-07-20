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
            'type' => TransactionType::TOPUP->value,
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

    public function test_charge_updated_after_cash_balance_reconciliation_dispatches_wallester_job(): void
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

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class, function (FundWallesterTopupFromStripeJob $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });

        $expectedPlatformFee = $feeConfig->fresh()->calculatePlatformFee($amount);
        $expectedNetAmount = $amount - $stripeFeeFromBalance - $expectedPlatformFee;

        $transaction->refresh();
        $this->assertSame('bank_transfer', $transaction->payment_method_type);
        $this->assertSame($stripeFeeFromBalance, (int) $transaction->stripe_fee);
        $this->assertSame($expectedPlatformFee, (int) $transaction->platform_fee);
        $this->assertSame($expectedNetAmount, (int) $transaction->net_amount);
        $this->assertSame($feeConfig->id, (int) $transaction->stripe_payment_method_fee_id);
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
            'livemode' => false,
            'net_amount' => 100,
            'type' => 'funded',
        ];
    }
}

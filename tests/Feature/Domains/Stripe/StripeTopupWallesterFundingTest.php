<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Customer\Jobs\SendCustomerNotificationEmailJob;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class StripeTopupWallesterFundingTest extends TestCase
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

    public function test_charge_updated_dispatches_wallester_topup_job_for_eligible_topup(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_topup_customer');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_topup_1',
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        $service = app(StripeService::class);
        $service->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_topup_1',
                    'payment_intent' => 'pi_topup_1',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_topup_1',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class, function (FundWallesterTopupFromStripeJob $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });
    }

    public function test_charge_updated_credits_pending_and_ledger_for_eligible_topup(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createPlatformCustomer();
        $this->createStripeAccount($customer);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_topup_customer');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_topup_ledger',
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_topup_ledger',
                    'payment_intent' => 'pi_topup_ledger',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_topup_ledger',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        $transaction = StripeTransaction::query()->where('stripe_payment_intent_id', 'pi_topup_ledger')->firstOrFail();

        $this->assertNotNull($transaction->balance_settled_at);
        $this->assertSame(4_875, (int) $transaction->net_amount);
        $this->assertSame(25, (int) $transaction->platform_fee);
        $this->assertSame(PartnerFundingStatus::Pending, $transaction->partner_funding_status);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(0, (int) $balance->available);
        $this->assertSame(4_875, (int) $balance->pending);

        $this->assertTrue(
            LedgerEntry::query()
                ->where('customer_id', $customer->id)
                ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
                ->where('amount', 4_875)
                ->exists()
        );
    }

    public function test_charge_updated_does_not_dispatch_for_non_topup(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createStripeAccount($customer);
        $this->createWallesterAccount($customer);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::CARD,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_card_1',
        ]);

        $this->mockStripeGatewayForChargeUpdated();

        app(StripeService::class)->handleWebhook([
            'type' => 'charge.updated',
            'data' => [
                'object' => [
                    'id' => 'ch_card_1',
                    'payment_intent' => 'pi_card_1',
                    'status' => 'succeeded',
                    'paid' => true,
                    'balance_transaction' => 'txn_card_1',
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'PT']],
                ],
            ],
        ]);

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);
    }

    public function test_topup_job_transfers_net_amount_and_is_idempotent(): void
    {
        Bus::fake([SendCustomerNotificationEmailJob::class]);

        $customer = $this->createActiveCustomer();
        $platform = $this->createPlatformCustomer();
        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
        ]);
        $toAccount = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_topup_customer');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 3_000,
            'stripe_fee' => 100,
            'platform_fee' => 25,
            'net_amount' => 2_875,
            'immediate_gross_amount' => 3_000,
            'wallester_funding_net_amount' => 2_875,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'balance_settled_at' => now(),
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 2_875,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => ($payload['amount'] ?? null) === 28.75), Mockery::any())
            ->andReturn(['id' => 'pay_topup_1']);
        $this->expectWallesterBalanceSyncAfterMasterTransfer(
            gateway: $gateway,
            masterAccountId: 'master-eur-uuid',
            toAccountId: 'wal_topup_customer',
            toCustomerId: $customer->id,
            masterBalance: ['balance' => 500.00, 'available_amount' => 500.00],
            toBalance: ['balance' => 28.75, 'available_amount' => 28.75],
        );

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $job = new FundWallesterTopupFromStripeJob($transaction->id);
        $job->handle(app(FundingPartnerMovementService::class), app(TopupWallesterFundingPolicy::class));
        $job->handle(app(FundingPartnerMovementService::class), app(TopupWallesterFundingPolicy::class));

        $this->assertDatabaseCount('funding_partner_movements', 2);
        $this->assertDatabaseHas('funding_partner_movements', [
            'amount' => 2_875,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':out',
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);

        $transaction->refresh();
        $this->assertSame(PartnerFundingStatus::Completed, $transaction->partner_funding_status);
        $this->assertNotNull($transaction->partner_funded_at);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(2_875, (int) $balance->available);
        $this->assertSame(0, (int) $balance->pending);

        $master->refresh();
        $toAccount->refresh();
        $this->assertSame(50_000, (int) $master->available_amount);
        $this->assertSame(2_875, (int) $toAccount->available_amount);

        Bus::assertNotDispatched(SendCustomerNotificationEmailJob::class);
    }

    public function test_topup_job_sends_platform_fee_to_sub_master_account(): void
    {
        Bus::fake([SendCustomerNotificationEmailJob::class]);

        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => '626c80a3-f8e7-4627-8bcf-ca73f9944abf',
        ]);

        $customer = $this->createActiveCustomer();
        $platform = $this->createPlatformCustomer();
        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
        ]);
        $feeAccount = $this->createWallesterAccount(
            $platform,
            IntegrationAccountStatus::ACTIVE,
            '626c80a3-f8e7-4627-8bcf-ca73f9944abf',
            [
                'is_main' => false,
                'receiver_name' => 'Sub Conta Master',
                'currency_code' => 'EUR',
                'funding_currency' => 'EUR',
                'product_id' => 'prod_fee_'.$platform->id,
                'company_id' => 'comp_fee_'.$platform->id,
            ],
        );
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_topup_customer');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 3_000,
            'stripe_fee' => 100,
            'platform_fee' => 25,
            'net_amount' => 2_875,
            'immediate_gross_amount' => 3_000,
            'wallester_funding_net_amount' => 2_875,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'balance_settled_at' => now(),
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 2_875,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return ($payload['from_account_id'] ?? null) === 'master-eur-uuid'
                    && ($payload['to_account_id'] ?? null) === 'wal_topup_customer'
                    && ($payload['amount'] ?? null) === 28.75;
            }), Mockery::any())
            ->andReturn(['id' => 'pay_topup_1']);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return ($payload['from_account_id'] ?? null) === 'master-eur-uuid'
                    && ($payload['to_account_id'] ?? null) === '626c80a3-f8e7-4627-8bcf-ca73f9944abf'
                    && ($payload['amount'] ?? null) === 0.25
                    && ($payload['description'] ?? null) === 'Taxa Yoop';
            }), 'platform')
            ->andReturn(['id' => 'pay_platform_fee_1']);
        $gateway->shouldReceive('getAccount')->andReturn([
            'available_amount' => 500.00,
            'balance' => 500.00,
        ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $job = new FundWallesterTopupFromStripeJob($transaction->id);
        $job->handle(app(FundingPartnerMovementService::class), app(TopupWallesterFundingPolicy::class));
        $job->handle(app(FundingPartnerMovementService::class), app(TopupWallesterFundingPolicy::class));

        $this->assertDatabaseHas('funding_partner_movements', [
            'amount' => 2_875,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':out',
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'amount' => 25,
            'funding_account_id' => $feeAccount->id,
            'idempotency_key' => 'stripe-platform-fee:'.$transaction->id.':in',
            'status' => FundingPartnerMovementStatus::Completed->value,
            'description' => 'Taxa Yoop',
        ]);
    }

    public function test_topup_job_marks_awaiting_liquidity_on_insufficient_main_funds(): void
    {
        $customer = $this->createActiveCustomer();
        $platform = $this->createPlatformCustomer();
        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_topup_customer');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'immediate_gross_amount' => 2_000,
            'wallester_funding_net_amount' => 2_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'balance_settled_at' => now(),
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 2_000,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->andThrow(new \RuntimeException('Wallester API error: {"error_code":20005,"message":"not enough funds on account available amount"}'));

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        try {
            (new FundWallesterTopupFromStripeJob($transaction->id))
                ->handle(app(FundingPartnerMovementService::class), app(TopupWallesterFundingPolicy::class));
            $this->fail('Expected transfer exception.');
        } catch (\Throwable) {
            // expected
        }

        $transaction->refresh();
        $this->assertSame(PartnerFundingStatus::AwaitingLiquidity, $transaction->partner_funding_status);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':out',
            'status' => FundingPartnerMovementStatus::Failed->value,
        ]);
    }

    private function createActiveCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }

    private function createPlatformCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'email' => 'master@yoop.com',
            'username' => 'platform',
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

<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Jobs\ProcessStripePayoutPaidJob;
use App\Domains\Stripe\Jobs\ProcessStripeWebhook;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\SettlementService;
use App\Domains\Wallet\Services\StripePayoutSettlementService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class StripePayoutPaidSettlementTest extends TestCase
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

    public function test_payout_paid_webhook_dispatches_settlement_job(): void
    {
        Bus::fake([ProcessStripePayoutPaidJob::class]);

        app(StripeService::class)->handleWebhook([
            'type' => 'payout.paid',
            'livemode' => true,
            'data' => [
                'object' => [
                    'id' => 'po_live_1',
                    'object' => 'payout',
                    'status' => 'paid',
                    'amount' => 144,
                    'currency' => 'eur',
                    'livemode' => true,
                ],
            ],
        ]);

        Bus::assertDispatched(ProcessStripePayoutPaidJob::class, function (ProcessStripePayoutPaidJob $job): bool {
            return $job->payoutId === 'po_live_1'
                && $job->currency === 'eur'
                && $job->livemode === true;
        });
    }

    public function test_test_mode_payout_paid_webhook_does_not_dispatch_settlement(): void
    {
        Bus::fake([ProcessStripePayoutPaidJob::class]);

        app(StripeService::class)->handleWebhook([
            'type' => 'payout.paid',
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => 'po_test_1',
                    'object' => 'payout',
                    'status' => 'paid',
                    'amount' => 144,
                    'currency' => 'eur',
                    'livemode' => false,
                ],
            ],
        ]);

        Bus::assertNotDispatched(ProcessStripePayoutPaidJob::class);
    }

    public function test_process_stripe_webhook_job_enqueues_payout_even_if_type_key_is_missing(): void
    {
        Bus::fake([ProcessStripePayoutPaidJob::class]);

        $job = new ProcessStripeWebhook([
            'livemode' => true,
            'data' => [
                'object' => [
                    'object' => 'payout',
                    'id' => 'po_live_shape',
                    'status' => 'paid',
                    'amount' => 100,
                    'currency' => 'eur',
                    'livemode' => true,
                ],
            ],
        ]);

        $job->handle(app(StripeService::class));

        Bus::assertDispatched(ProcessStripePayoutPaidJob::class, function (ProcessStripePayoutPaidJob $job): bool {
            return $job->payoutId === 'po_live_shape' && $job->livemode === true;
        });
    }

    public function test_process_stripe_webhook_job_ignores_test_mode(): void
    {
        Bus::fake([ProcessStripePayoutPaidJob::class]);

        $job = new ProcessStripeWebhook([
            'livemode' => false,
            'data' => [
                'object' => [
                    'object' => 'payout',
                    'id' => 'po_test_shape',
                    'status' => 'paid',
                    'amount' => 100,
                    'currency' => 'eur',
                    'livemode' => false,
                ],
            ],
        ]);

        $job->handle(app(StripeService::class));

        Bus::assertNotDispatched(ProcessStripePayoutPaidJob::class);
    }

    public function test_recover_recent_paid_payouts_dispatches_settlement_jobs(): void
    {
        Bus::fake([ProcessStripePayoutPaidJob::class]);

        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('listPayouts')
            ->once()
            ->withArgs(fn (int $createdGte, string $currency, string $mode): bool => $mode === 'live' && $currency === 'eur')
            ->andReturn(['status' => 'success', 'message' => null, 'data' => ['data' => []]]);
        $gateway->shouldReceive('listPayouts')
            ->once()
            ->withArgs(fn (int $createdGte, string $currency, string $mode): bool => $mode === 'test' && $currency === 'eur')
            ->andReturn([
                'status' => 'success',
                'message' => null,
                'data' => [
                    'data' => [
                        [
                            'id' => 'po_recovered_test',
                            'status' => 'paid',
                            'currency' => 'eur',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);

        $stats = app(StripePayoutSettlementService::class)->recoverRecentPaidPayouts(now()->subDay()->timestamp);

        $this->assertSame(1, $stats['dispatched']);
        $this->assertSame(1, $stats['test']);
        $this->assertSame(0, $stats['live']);

        Bus::assertDispatched(ProcessStripePayoutPaidJob::class, function (ProcessStripePayoutPaidJob $job): bool {
            return $job->payoutId === 'po_recovered_test' && $job->livemode === false;
        });
    }

    public function test_payout_settlement_arms_merchant_for_wallester_funding(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_merchant_payout');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 100,
            'platform_fee' => 0,
            'net_amount' => 4_900,
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => 0,
            'stripe_deferred_net_amount' => 4_900,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => 'pi_merchant_payout',
            'stripe_charge_id' => 'ch_merchant_payout',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
            'available_on' => now()->toDateString(),
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 0,
            'pending' => 4_900,
        ]);

        $this->mockPayoutBalanceTransactions([
            [
                'id' => 'txn_payout_row',
                'type' => 'payout',
                'source' => 'po_merchant_1',
                'amount' => -4_900,
            ],
            [
                'id' => 'txn_charge_merchant',
                'type' => 'charge',
                'source' => 'ch_merchant_payout',
                'amount' => 5_000,
                'fee' => 100,
                'net' => 4_900,
            ],
        ]);

        $stats = app(StripePayoutSettlementService::class)->settlePayout('po_merchant_1', 'eur', false);

        $this->assertSame(1, $stats['matched']);
        $this->assertSame(1, $stats['wallester_dispatched']);
        $this->assertSame(0, $stats['missing']);

        $transaction->refresh();
        $this->assertSame(PartnerFundingStatus::Pending, $transaction->partner_funding_status);
        $this->assertSame(4_900, (int) $transaction->wallester_funding_net_amount);
        $this->assertSame(0, (int) $transaction->stripe_deferred_net_amount);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(4_900, (int) $balance->wallester_allocated);
        $this->assertSame(0, (int) $balance->pending);
        $this->assertSame(4_900, (int) $balance->wise_allocated);

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class, function (FundWallesterTopupFromStripeJob $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });
    }

    public function test_payout_settlement_credits_deferred_topup(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_deferred_payout');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 15_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 15_000,
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => 0,
            'stripe_deferred_net_amount' => 15_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => 'pi_deferred_payout',
            'stripe_charge_id' => 'ch_deferred_payout',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->toDateString(),
        ]);

        $this->mockPayoutBalanceTransactions([
            [
                'id' => 'txn_charge_deferred',
                'type' => 'charge',
                'source' => 'ch_deferred_payout',
                'amount' => 15_000,
                'fee' => 0,
                'net' => 15_000,
            ],
        ]);

        $stats = app(StripePayoutSettlementService::class)->settlePayout('po_deferred_1', 'eur', false);

        $this->assertSame(1, $stats['matched']);
        $this->assertSame(1, $stats['deferred_credited']);

        $transaction->refresh();
        $this->assertNotNull($transaction->balance_settled_at);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(15_000, (int) $balance->wallester_allocated);
        $this->assertSame(15_000, (int) $balance->wise_allocated);

        $this->assertTrue(
            LedgerEntry::query()
                ->where('customer_id', $customer->id)
                ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
                ->where('amount', 15_000)
                ->exists()
        );

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class, function (FundWallesterTopupFromStripeJob $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });
    }

    public function test_payout_settlement_mirrors_quota_topup_without_a_second_wallester_transfer(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_quota_payout');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 125,
            'platform_fee' => 0,
            'net_amount' => 4_875,
            'immediate_gross_amount' => 5_000,
            'wallester_funding_net_amount' => 4_875,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => 'pi_quota_payout',
            'stripe_charge_id' => 'ch_quota_payout',
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'available_on' => now()->toDateString(),
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 0,
            'pending' => 4_875,
            'wise_allocated' => 0,
        ]);

        $this->mockPayoutBalanceTransactions([
            [
                'id' => 'txn_charge_quota',
                'type' => 'charge',
                'source' => 'ch_quota_payout',
                'amount' => 5_000,
                'fee' => 125,
                'net' => 4_875,
            ],
        ]);

        $stats = app(StripePayoutSettlementService::class)->settlePayout('po_quota_1', 'eur', false);

        $this->assertSame(1, $stats['matched']);
        $this->assertSame(0, $stats['wallester_dispatched']);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(4_875, (int) $balance->wallester_allocated);
        $this->assertSame(0, (int) $balance->pending);
        $this->assertSame(4_875, (int) $balance->wise_allocated);

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);
    }

    public function test_repeating_payout_settlement_does_not_duplicate_funding_or_wise_allocation(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_repeat_payout');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 15_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 15_000,
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => 0,
            'stripe_deferred_net_amount' => 15_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => 'pi_repeat_payout',
            'stripe_charge_id' => 'ch_repeat_payout',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->toDateString(),
        ]);

        $this->mockPayoutBalanceTransactions([
            [
                'id' => 'txn_charge_repeat',
                'type' => 'charge',
                'source' => 'ch_repeat_payout',
                'amount' => 15_000,
                'fee' => 0,
                'net' => 15_000,
            ],
        ], 2);

        $settlement = app(StripePayoutSettlementService::class);
        $settlement->settlePayout('po_repeat_1', 'eur', false);
        $settlement->settlePayout('po_repeat_1', 'eur', false);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(15_000, (int) $balance->wallester_allocated);
        $this->assertSame(15_000, (int) $balance->wise_allocated);
        $this->assertSame(1, LedgerEntry::query()->where('customer_id', $customer->id)->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)->count());

        Bus::assertDispatchedTimes(FundWallesterTopupFromStripeJob::class, 1);
    }

    public function test_daily_settlement_does_not_credit_by_available_on(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 15_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 15_000,
            'stripe_deferred_net_amount' => 15_000,
            'wallester_funding_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_charge_id' => 'ch_stale_deferred',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->subDay()->toDateString(),
            'balance_settled_at' => null,
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'stripe_deferred_net_amount' => 2_000,
            'wallester_funding_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_charge_id' => 'ch_stale_merchant',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
            'available_on' => now()->subDay()->toDateString(),
            'balance_settled_at' => null,
        ]);

        $result = app(SettlementService::class)->runDailySettlement(today());

        $this->assertSame(0, $result['retried']);
        $this->assertSame(1, $result['awaiting_payout_stale']);
        $this->assertSame(0, $result['bank_transfers_released']);

        $this->assertNull(
            CustomerBalance::query()->where('customer_id', $customer->id)->first()
        );

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);
    }

    public function test_daily_settlement_releases_due_bank_transfer_without_waiting_for_payout(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::BANK_TRANSFER,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'stripe_deferred_net_amount' => 298,
            'wallester_funding_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_charge_id' => 'ch_bank_release',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->subDay(),
            'balance_settled_at' => null,
        ]);

        $result = app(SettlementService::class)->runDailySettlement(today());

        $transaction->refresh();

        $this->assertSame(1, $result['bank_transfers_released']);
        $this->assertSame(PartnerFundingStatus::Pending, $transaction->partner_funding_status);
        $this->assertSame(298, (int) $transaction->wallester_funding_net_amount);
        $this->assertSame(0, (int) $transaction->stripe_deferred_net_amount);
        $this->assertNull($transaction->balance_settled_at);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PAYMENT_RECEIVED->value,
            'amount' => 298,
            'reference_id' => $transaction->id,
        ]);
        $this->assertDatabaseHas('rail_mirror_events', [
            'idempotency_key' => 'stripe_liquidity_wise:'.$transaction->id,
        ]);
        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class);
    }

    public function test_payout_paid_does_not_release_a_bank_transfer(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $customer = $this->createActiveCustomer();

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::BANK_TRANSFER,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'stripe_deferred_net_amount' => 298,
            'wallester_funding_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_charge_id' => 'ch_bank_payout',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'available_on' => now()->subDay(),
            'balance_settled_at' => null,
        ]);

        $result = app(StripePayoutSettlementService::class)->settleTransaction($transaction, 'po_bank');

        $this->assertTrue($result['skipped']);
        $this->assertFalse($result['wallester_dispatched']);
        $this->assertNull($transaction->fresh()->balance_settled_at);
        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);
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

    /**
     * @param  list<array<string, mixed>>  $balanceTransactions
     */
    private function mockPayoutBalanceTransactions(array $balanceTransactions, int $times = 1): void
    {
        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('getTransactionsByIdPayout')
            ->times($times)
            ->andReturn([
                'status' => 'success',
                'message' => null,
                'data' => [
                    'object' => 'list',
                    'data' => $balanceTransactions,
                    'has_more' => false,
                ],
            ]);

        $this->app->instance(StripeGatewayInterface::class, $gateway);
    }
}

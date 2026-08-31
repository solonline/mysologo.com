<?php

namespace Tests\Feature\Domains\Stripe;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Jobs\ProcessStripePayoutPaidJob;
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

        Bus::assertDispatched(ProcessStripePayoutPaidJob::class, function (ProcessStripePayoutPaidJob $job): bool {
            return $job->payoutId === 'po_test_1'
                && $job->currency === 'eur'
                && $job->livemode === false;
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
            'available' => 0,
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

        $this->assertSame(15_000, (int) $balance->available);

        $this->assertTrue(
            LedgerEntry::query()
                ->where('customer_id', $customer->id)
                ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
                ->where('amount', 15_000)
                ->exists()
        );

        Bus::assertNotDispatched(FundWallesterTopupFromStripeJob::class);
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

        $this->assertNull(
            CustomerBalance::query()->where('customer_id', $customer->id)->first()
        );

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
    private function mockPayoutBalanceTransactions(array $balanceTransactions): void
    {
        $gateway = Mockery::mock(StripeGatewayInterface::class);
        $gateway->shouldReceive('getTransactionsByIdPayout')
            ->once()
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

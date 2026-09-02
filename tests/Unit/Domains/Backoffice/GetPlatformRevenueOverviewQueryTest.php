<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Queries\GetPlatformRevenueOverviewQuery;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class GetPlatformRevenueOverviewQueryTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_overview_sums_stripe_platform_fees_without_wallet_ledger(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_created_at' => now(),
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertNotNull($summary);
        $this->assertSame('EUR', $summary->currency);
        $this->assertSame(10, $summary->accruedCents);
        $this->assertSame(0, $summary->settledCents);
        $this->assertSame(10, $summary->pendingCents);
        $this->assertSame(0, $summary->fxCents);
        $this->assertSame(10, $summary->billedCents());
        $this->assertSame(1, $summary->transactionCount);
        $this->assertSame(0, $summary->fxTransactionCount);
        $this->assertSame(1, $summary->billedTransactionCount());
        $this->assertSame(0, $summary->settledTransactionCount);
        $this->assertSame(1, $summary->pendingTransactionCount);
    }

    public function test_overview_splits_settled_and_pending_platform_fees(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 10,
            'net_amount' => 990,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'balance_settled_at' => now(),
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 25,
            'net_amount' => 1_975,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'balance_settled_at' => null,
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertSame(35, $summary?->accruedCents);
        $this->assertSame(35, $summary?->billedCents());
        $this->assertSame(10, $summary?->settledCents);
        $this->assertSame(25, $summary?->pendingCents);
        $this->assertSame(1, $summary?->settledTransactionCount);
        $this->assertSame(1, $summary?->pendingTransactionCount);
    }

    public function test_overview_includes_wallester_fx_platform_fees(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_fx_revenue');

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_fx_revenue',
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 454,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:fx-revenue:out',
            'partner_external_id' => 'fx-revenue',
            'description' => 'LidlGB',
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertSame(0, $summary?->accruedCents);
        $this->assertSame(5, $summary?->fxCents);
        $this->assertSame(5, $summary?->monthFxCents);
        $this->assertSame(5, $summary?->billedCents());
        $this->assertSame(1, $summary?->fxTransactionCount);
        $this->assertSame(1, $summary?->billedTransactionCount());
    }

    public function test_overview_billed_total_sums_stripe_and_fx_modalities(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_fx_billed');

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 369,
            'stripe_fee' => 34,
            'platform_fee' => 201,
            'net_amount' => 134,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_created_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_fx_billed',
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 454,
            'currency' => 'EUR',
            'fees_amount' => 9,
            'platform_fees_amount' => 314,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:fx-billed:out',
            'partner_external_id' => 'fx-billed',
            'description' => 'LidlGB',
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertSame(201, $summary?->accruedCents);
        $this->assertSame(314, $summary?->fxCents);
        $this->assertSame(515, $summary?->billedCents());
        $this->assertSame(515, $summary?->monthBilledCents());
        $this->assertSame(2, $summary?->billedTransactionCount());

        $thisMonth = now()->format('Y-m');
        $monthRow = collect($summary?->monthlyBreakdown ?? [])->firstWhere('month', $thisMonth);
        $this->assertNotNull($monthRow);
        $this->assertSame(515, $monthRow['amount_cents']);
        $this->assertSame(314, $monthRow['fx_cents']);
        $this->assertSame(201, $monthRow['pending_cents']);
    }

    public function test_overview_excludes_stripe_fees_before_accounting_cutoff(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_100,
            'stripe_fee' => 61,
            'platform_fee' => 6,
            'net_amount' => 1_033,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_created_at' => '2026-07-22 10:46:20',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 369,
            'stripe_fee' => 34,
            'platform_fee' => 201,
            'net_amount' => 134,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_created_at' => '2026-08-24 15:22:03',
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertSame(201, $summary?->accruedCents);
        $this->assertSame(201, $summary?->billedCents());
        $this->assertSame(1, $summary?->transactionCount);
    }

    private function createPlatformCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-revenue-test@yoop.com',
            'username' => 'platform_revenue_test',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);
    }
}

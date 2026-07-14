<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Queries\GetPlatformRevenueOverviewQuery;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\Customer;
use App\Models\LedgerEntry;
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

    public function test_overview_sums_platform_fees_and_matches_ledger(): void
    {
        $platform = $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        $transaction = StripeTransaction::query()->create([
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

        LedgerEntry::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'direction' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT,
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'description' => PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$transaction->id,
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();
        $summary = $overview->primary();

        $this->assertNotNull($summary);
        $this->assertSame('EUR', $summary->currency);
        $this->assertSame(10, $summary->accruedCents);
        $this->assertSame(10, $summary->ledgerCents);
        $this->assertSame(1, $summary->transactionCount);
        $this->assertTrue($overview->ledgerMatchesStripe);
    }

    public function test_overview_detects_ledger_mismatch(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 300,
            'platform_fee' => 20,
            'net_amount' => 1_680,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $overview = app(GetPlatformRevenueOverviewQuery::class)->execute();

        $this->assertFalse($overview->ledgerMatchesStripe);
        $this->assertSame(20, $overview->primary()?->accruedCents);
        $this->assertSame(0, $overview->primary()?->ledgerCents);
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

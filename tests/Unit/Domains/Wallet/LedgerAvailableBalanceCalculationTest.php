<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class LedgerAvailableBalanceCalculationTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_recalculate_balance_excludes_pending_bucket_double_count(): void
    {
        $customer = $this->createRetailCustomer();
        $ledger = app(LedgerService::class);
        $tx = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 100,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 72,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $ledger->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 72,
            reference: $tx,
            description: 'pending',
        );

        $ledger->releasePendingToAvailable(
            customerId: $customer->id,
            currency: 'EUR',
            amount: 72,
            reference: $tx,
            description: 'released',
        );

        $available = (int) CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->value('available');

        $this->assertSame(72, $available);
        $this->assertSame(72, $ledger->recalculateBalance($customer->id, 'EUR'));
        $this->assertTrue($ledger->isBalanceConsistent($customer->id, 'EUR'));
    }

    public function test_platform_revenue_subtracts_fee_reversals(): void
    {
        Cache::flush();

        $platform = Customer::query()->create([
            'name' => 'Platform',
            'email' => 'platform-ledger-calc@yoop.test',
            'username' => 'platform_ledger_calc',
            'type' => 'platform',
            'status' => 'ACTIVE',
            'currency' => 'EUR',
            'language' => 'pt',
            'email_verified_at' => now(),
        ]);
        Cache::flush();
        $retail = $this->createRetailCustomer();
        $ledger = app(LedgerService::class);

        $live = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1000,
            'stripe_fee' => 10,
            'platform_fee' => 11,
            'net_amount' => 979,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $purged = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 440,
            'stripe_fee' => 36,
            'platform_fee' => 85,
            'net_amount' => 319,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $ledger->record(
            customerId: $platform->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 11,
            reference: $live,
            description: PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$live->id,
        );
        $ledger->record(
            customerId: $platform->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 85,
            reference: $purged,
            description: PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$purged->id,
        );
        $ledger->record(
            customerId: $platform->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_DEBIT,
            amount: 85,
            reference: $purged,
            description: PlatformRevenueService::LEDGER_PLATFORM_FEE_REVERSAL_PREFIX.$purged->id,
        );

        $purged->delete();

        $summary = collect(app(PlatformRevenueService::class)->summarize())
            ->firstWhere('currency', 'EUR');

        $this->assertNotNull($summary);
        $this->assertSame(11, $summary['accrued_cents']);
        $this->assertSame(11, $summary['ledger_cents']);
        $this->assertTrue($summary['ledger_matches_stripe']);
    }
}

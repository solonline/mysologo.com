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
            ->value('wallester_allocated');

        $this->assertSame(72, $available);
        $this->assertSame(72, $ledger->recalculateBalance($customer->id, 'EUR'));
        $this->assertTrue($ledger->isBalanceConsistent($customer->id, 'EUR'));

        $pendingEntry = LedgerEntry::query()->where('description', 'pending')->firstOrFail();
        $this->assertSame(0, (int) $pendingEntry->available_before);
        $this->assertSame(0, (int) $pendingEntry->available_after);
        $this->assertSame(0, (int) $pendingEntry->pending_before);
        $this->assertSame(72, (int) $pendingEntry->pending_after);
        $this->assertSame(0, (int) $pendingEntry->balance_after);

        $releasedEntry = LedgerEntry::query()->where('description', 'released')->firstOrFail();
        $this->assertSame(0, (int) $releasedEntry->available_before);
        $this->assertSame(72, (int) $releasedEntry->available_after);
        $this->assertSame(72, (int) $releasedEntry->pending_before);
        $this->assertSame(0, (int) $releasedEntry->pending_after);
        $this->assertSame(72, (int) $releasedEntry->balance_after);
    }

    public function test_record_and_reverse_pending_keep_available_snapshots_stable(): void
    {
        $customer = $this->createRetailCustomer();
        $ledger = app(LedgerService::class);

        $credit = $ledger->record(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::MANUAL_CREDIT,
            amount: 500,
            description: 'seed available',
        );
        $this->assertSame(0, (int) $credit->available_before);
        $this->assertSame(500, (int) $credit->available_after);
        $this->assertSame(0, (int) $credit->pending_before);
        $this->assertSame(0, (int) $credit->pending_after);

        $pending = $ledger->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 200,
            description: 'awaiting payout',
        );
        $this->assertSame(500, (int) $pending->available_before);
        $this->assertSame(500, (int) $pending->available_after);
        $this->assertSame(0, (int) $pending->pending_before);
        $this->assertSame(200, (int) $pending->pending_after);

        $reversed = $ledger->reversePendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            amount: 80,
            description: 'purge part',
        );
        $this->assertSame(500, (int) $reversed->available_before);
        $this->assertSame(500, (int) $reversed->available_after);
        $this->assertSame(200, (int) $reversed->pending_before);
        $this->assertSame(120, (int) $reversed->pending_after);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();
        $this->assertSame(500, (int) $balance->wallester_allocated);
        $this->assertSame(120, (int) $balance->pending);
    }

    public function test_platform_revenue_ignores_purged_stripe_fees(): void
    {
        Cache::flush();

        Customer::query()->create([
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

        StripeTransaction::query()->create([
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

        $purged->delete();

        $summary = collect(app(PlatformRevenueService::class)->summarize())
            ->firstWhere('currency', 'EUR');

        $this->assertNotNull($summary);
        $this->assertSame(11, $summary['accrued_cents']);
        $this->assertSame(0, $summary['fx_cents']);
    }
}

<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\StripePaymentWalletService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripePaymentWalletPendingTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_eligible_topup_credits_pending_bucket(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_pending');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 100,
            'platform_fee' => 25,
            'net_amount' => 4_875,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        app(StripePaymentWalletService::class)->creditFromStripePayment($transaction);

        $transaction->refresh();
        $this->assertSame(PartnerFundingStatus::Pending, $transaction->partner_funding_status);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(0, (int) $balance->wallester_allocated);
        $this->assertSame(4_875, (int) $balance->pending);
    }

    public function test_large_topup_defers_entire_amount_to_stripe_settlement(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_not_required');

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
        ]);

        app(StripePaymentWalletService::class)->creditFromStripePayment($transaction);

        $transaction->refresh();
        $this->assertSame(PartnerFundingStatus::NotRequired, $transaction->partner_funding_status);
        $this->assertNull($transaction->balance_settled_at);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertNull($balance);
    }

    public function test_bank_transfer_credits_pending_until_stripe_liquidity(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::BANK_TRANSFER,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'available_on' => now()->addDays(5),
            'balance_settled_at' => null,
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'stripe_deferred_net_amount' => 298,
            'wallester_funding_net_amount' => 0,
        ]);

        app(StripePaymentWalletService::class)->creditFromStripePayment($transaction);
        app(StripePaymentWalletService::class)->creditFromStripePayment($transaction->fresh());

        $transaction->refresh();
        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $this->assertSame(PartnerFundingStatus::NotRequired, $transaction->partner_funding_status);
        $this->assertNull($transaction->balance_settled_at);
        $this->assertSame(0, (int) $balance->wallester_allocated);
        $this->assertSame(298, (int) $balance->pending);
    }

    public function test_platform_fee_does_not_credit_platform_wallet(): void
    {
        $platform = Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-wallet-fee@yoop.test',
            'username' => 'platform_wallet_fee',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 100,
            'pending' => 0,
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_no_platform_fee');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 100,
            'platform_fee' => 25,
            'net_amount' => 4_875,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        app(StripePaymentWalletService::class)->creditFromStripePayment($transaction);

        $this->assertSame(100, (int) CustomerBalance::query()
            ->where('customer_id', $platform->id)
            ->where('currency', 'EUR')
            ->value('wallester_allocated'));

        $this->assertDatabaseMissing('ledger_entries', [
            'customer_id' => $platform->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
        ]);
    }
}

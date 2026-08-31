<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class TopupWallesterFundingPolicyTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_first_topup_within_daily_quota_funds_wallester_immediately(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_policy_1');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_000,
            'stripe_fee' => 125,
            'platform_fee' => 0,
            'net_amount' => 4_875,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $allocation = app(TopupWallesterFundingPolicy::class)->allocate($transaction);

        $this->assertSame(5_000, $allocation->immediateGrossCents);
        $this->assertSame(4_875, $allocation->wallesterFundingNetCents);
        $this->assertSame(0, $allocation->stripeDeferredNetCents);
    }

    public function test_large_topup_defers_entire_amount_to_stripe_when_exceeding_daily_quota(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_policy_2');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 15_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 15_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $allocation = app(TopupWallesterFundingPolicy::class)->allocate($transaction);

        $this->assertSame(0, $allocation->immediateGrossCents);
        $this->assertSame(0, $allocation->wallesterFundingNetCents);
        $this->assertSame(15_000, $allocation->stripeDeferredNetCents);
    }

    public function test_partial_daily_quota_allows_second_topup_that_fits_and_defers_larger_one(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_policy_4');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'immediate_gross_amount' => 5_000,
            'wallester_funding_net_amount' => 5_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $fitsQuota = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $exceedsQuota = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_100,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_100,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $fitsAllocation = app(TopupWallesterFundingPolicy::class)->allocate($fitsQuota);
        $exceedsAllocation = app(TopupWallesterFundingPolicy::class)->allocate($exceedsQuota);

        $this->assertSame(5_000, $fitsAllocation->wallesterFundingNetCents);
        $this->assertSame(0, $fitsAllocation->stripeDeferredNetCents);
        $this->assertSame(0, $exceedsAllocation->wallesterFundingNetCents);
        $this->assertSame(5_100, $exceedsAllocation->stripeDeferredNetCents);
    }

    public function test_daily_quota_exhaustion_defers_entire_topup_to_stripe(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_policy_3');

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 10_000,
            'immediate_gross_amount' => 10_000,
            'wallester_funding_net_amount' => 10_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 5_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'created_at' => now(),
        ]);

        $allocation = app(TopupWallesterFundingPolicy::class)->allocate($transaction);

        $this->assertSame(0, $allocation->immediateGrossCents);
        $this->assertSame(0, $allocation->wallesterFundingNetCents);
        $this->assertSame(5_000, $allocation->stripeDeferredNetCents);
    }

    public function test_is_retriable_skips_transactions_before_accounting_cutoff(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $historical = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 330,
            'stripe_fee' => 35,
            'platform_fee' => 0,
            'net_amount' => 295,
            'wallester_funding_net_amount' => 295,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingLiquidity,
            'stripe_created_at' => '2026-07-21 15:55:09',
        ]);

        $inPeriod = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'wallester_funding_net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingLiquidity,
            'stripe_created_at' => '2026-08-17 10:00:00',
        ]);

        $policy = app(TopupWallesterFundingPolicy::class);

        $this->assertTrue($policy->isBeforeAccountingCutoff($historical));
        $this->assertFalse($policy->isRetriable($historical));
        $this->assertFalse($policy->isBeforeAccountingCutoff($inPeriod));
        $this->assertTrue($policy->isRetriable($inPeriod));
    }
}

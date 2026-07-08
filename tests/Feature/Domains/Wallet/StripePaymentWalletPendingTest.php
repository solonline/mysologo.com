<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\StripePaymentWalletService;
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

        $this->assertSame(0, (int) $balance->available);
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
}

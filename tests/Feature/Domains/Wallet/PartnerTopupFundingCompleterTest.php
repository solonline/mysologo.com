<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\PartnerTopupFundingCompleter;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Models\CustomerBalance;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class PartnerTopupFundingCompleterTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_completed_wallester_topup_mirrors_the_available_amount_on_wise(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 5_000,
            'stripe_fee' => 125,
            'platform_fee' => 0,
            'net_amount' => 4_875,
            'immediate_gross_amount' => 5_000,
            'wallester_funding_net_amount' => 4_875,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 4_875,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        app(PartnerTopupFundingCompleter::class)->complete($transaction);

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->where('currency', 'EUR')->firstOrFail();

        $this->assertSame(4_875, (int) $balance->wallester_allocated);
        $this->assertSame(0, (int) $balance->pending);
        $this->assertSame(4_875, (int) $balance->wise_allocated);
        $this->assertDatabaseHas('rail_mirror_events', [
            'idempotency_key' => 'stripe_topup_wise:'.$transaction->id,
            'amount' => 4_875,
            'reason' => 'wallester_topup_available',
        ]);
    }

    public function test_completed_wallester_topup_does_not_mirror_wise_twice(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => TransactionDirection::Credit,
            'amount' => 3_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 3_000,
            'wallester_funding_net_amount' => 3_000,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Pending,
        ]);

        app(RailMirrorService::class)->creditAllocation(
            customerId: $customer->id,
            currency: 'EUR',
            amountCents: 3_000,
            idempotencyKey: 'stripe_liquidity_wise:'.$transaction->id,
            reason: 'bank_transfer_liquidity',
            reference: $transaction,
        );

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 3_000,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        app(PartnerTopupFundingCompleter::class)->complete($transaction);

        $this->assertSame(3_000, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wise_allocated'));
        $this->assertDatabaseMissing('rail_mirror_events', [
            'idempotency_key' => 'stripe_topup_wise:'.$transaction->id,
        ]);
    }
}

<?php

namespace Tests\Unit\Models;

use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class StripeTransactionFeesPendingTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_fees_pending_when_gross_set_but_fees_not_calculated(): void
    {
        $transaction = $this->makeTransaction([
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
        ]);

        $this->assertTrue($transaction->feesPending());
        $this->assertNull($transaction->resolvedNetAmount());
        $this->assertNull($transaction->resolvedFeeTotal());
    }

    public function test_fees_not_pending_after_charge_updated_values(): void
    {
        $transaction = $this->makeTransaction([
            'amount' => 5_000,
            'stripe_fee' => 100,
            'platform_fee' => 25,
            'net_amount' => 4_875,
        ]);

        $this->assertFalse($transaction->feesPending());
        $this->assertSame(4_875, $transaction->resolvedNetAmount());
        $this->assertSame(125, $transaction->resolvedFeeTotal());
    }

    public function test_transaction_resource_exposes_gross_available_and_fee(): void
    {
        $transaction = $this->makeTransaction([
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'status' => 'succeeded',
            'partner_funding_status' => 'completed',
        ]);

        $payload = (new TransactionResource($transaction))->resolve();

        $this->assertSame(300, $payload['amount']);
        $this->assertSame(298, $payload['net_amount']);
        $this->assertSame(2, $payload['fee']);
        $this->assertFalse($payload['fees_pending']);
        $this->assertSame('pending', $payload['status']);
        $this->assertArrayNotHasKey('partner_funding_status', $payload);
    }

    public function test_settled_topup_stays_succeeded_for_the_customer(): void
    {
        $transaction = $this->makeTransaction([
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'status' => 'succeeded',
            'balance_settled_at' => now(),
        ]);

        $this->assertSame('succeeded', $transaction->customerFacingStatus());
        $this->assertTrue($transaction->customerFundsAvailable());
    }

    public function test_bank_transfer_stays_pending_until_stripe_liquidity_date(): void
    {
        $transaction = $this->makeTransaction([
            'payment_method_type' => 'bank_transfer',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'status' => 'succeeded',
            'balance_settled_at' => now(),
            'available_on' => '2026-10-21',
        ]);

        $this->assertTrue($transaction->awaitsStripeLiquidityDate());
        $this->assertSame('pending', $transaction->customerFacingStatus());
        $this->assertFalse($transaction->customerFundsAvailable());
    }

    public function test_transaction_resource_exposes_null_net_while_fees_pending(): void
    {
        $transaction = $this->makeTransaction([
            'amount' => 3_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'status' => 'succeeded',
        ]);

        $payload = (new TransactionResource($transaction))->resolve();

        $this->assertTrue($payload['fees_pending']);
        $this->assertNull($payload['net_amount']);
        $this->assertNull($payload['fee']);
        $this->assertSame(3_000, $payload['amount']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeTransaction(array $overrides = []): StripeTransaction
    {
        $customer = $this->createRetailCustomer();

        return StripeTransaction::query()->create(array_merge([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'direction' => TransactionDirection::Credit,
            'payment_method_type' => 'card',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
        ], $overrides));
    }
}

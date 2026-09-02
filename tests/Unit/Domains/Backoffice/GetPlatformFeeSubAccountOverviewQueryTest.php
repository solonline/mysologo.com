<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Queries\GetPlatformFeeSubAccountOverviewQuery;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class GetPlatformFeeSubAccountOverviewQueryTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const FEE_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    public function test_splits_sub_balance_from_pending_liquidity_and_stripe(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'fee-overview@yoop.test']);
        $retail = $this->createRetailCustomer();

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 125,
        ]);
        $fee = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'available_amount' => 314,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);

        $funded = StripeTransaction::query()->create([
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
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funded_at' => now(),
            'stripe_created_at' => now(),
        ]);
        $awaitingPayout = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 246,
            'stripe_fee' => 33,
            'platform_fee' => 140,
            'net_amount' => 73,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
            'stripe_created_at' => now(),
        ]);
        $historical = StripeTransaction::query()->create([
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
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'stripe_created_at' => '2026-07-22 10:00:00',
        ]);

        $this->pendingFeeIn($fee, $funded, 201, ['pending_reason' => 'liquidity']);
        $this->pendingFeeIn($fee, $awaitingPayout, 140, ['pending_reason' => 'stripe_payout']);
        $this->pendingFeeIn($fee, $historical, 6, ['pending_reason' => 'stripe_balance']);

        $overview = app(GetPlatformFeeSubAccountOverviewQuery::class)->execute();

        $this->assertSame(314, $overview->onSubCents);
        $this->assertSame(201, $overview->awaitingLiquidityCents);
        $this->assertSame(140, $overview->awaitingStripeCents);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function pendingFeeIn($feeAccount, StripeTransaction $transaction, int $amount, array $metadata): void
    {
        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $feeAccount::class,
            'funding_account_id' => $feeAccount->id,
            'external_account_id' => $feeAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Pending,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe-platform-fee:'.$transaction->id.':in',
            'description' => 'Taxa Yoop',
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'metadata' => $metadata,
        ]);
    }
}

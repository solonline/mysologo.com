<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\PlatformFeeSubAccountOverview;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Throwable;

class GetPlatformFeeSubAccountOverviewQuery
{
    public function __construct(
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}

    public function execute(string $currency = 'EUR'): PlatformFeeSubAccountOverview
    {
        $currency = strtoupper($currency);

        try {
            $feeAccount = $this->wallesterDriver->getPlatformFeeAccount($currency);
            $masterAccount = $this->wallesterDriver->getPlatformMasterAccount($currency);
        } catch (Throwable) {
            return PlatformFeeSubAccountOverview::empty($currency);
        }

        $onSubCents = $feeAccount->is($masterAccount)
            ? 0
            : (int) ($feeAccount->available_amount ?? 0);

        [$awaitingLiquidityCents, $awaitingStripeCents] = $this->pendingFeeCents($currency);

        return new PlatformFeeSubAccountOverview(
            currency: $currency,
            onSubCents: $onSubCents,
            awaitingLiquidityCents: $awaitingLiquidityCents,
            awaitingStripeCents: $awaitingStripeCents,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pendingFeeCents(string $currency): array
    {
        $movements = FundingPartnerMovement::query()
            ->where('status', FundingPartnerMovementStatus::Pending)
            ->where('currency', $currency)
            ->where('idempotency_key', 'like', 'stripe-platform-fee:%:in')
            ->get(['id', 'amount', 'reference_id', 'metadata']);

        if ($movements->isEmpty()) {
            return [0, 0];
        }

        $transactions = StripeTransaction::query()
            ->whereIn('id', $movements->pluck('reference_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $awaitingLiquidity = 0;
        $awaitingStripe = 0;

        foreach ($movements as $movement) {
            $transaction = $transactions->get((int) $movement->reference_id);

            if ($transaction instanceof StripeTransaction && $this->fundingPolicy->isBeforeAccountingCutoff($transaction)) {
                continue;
            }

            $amount = (int) $movement->amount;

            if ($this->isAwaitingMasterLiquidity($transaction, $movement)) {
                $awaitingLiquidity += $amount;
            } else {
                $awaitingStripe += $amount;
            }
        }

        return [$awaitingLiquidity, $awaitingStripe];
    }

    private function isAwaitingMasterLiquidity(?StripeTransaction $transaction, FundingPartnerMovement $movement): bool
    {
        $reason = is_array($movement->metadata) ? ($movement->metadata['pending_reason'] ?? null) : null;

        if ($reason === 'liquidity') {
            return true;
        }

        if ($reason === 'stripe_payout' || $reason === 'stripe_balance') {
            return false;
        }

        if (! $transaction instanceof StripeTransaction) {
            return true;
        }

        return $transaction->partner_funding_status === PartnerFundingStatus::Completed
            || $transaction->partner_funded_at !== null;
    }
}

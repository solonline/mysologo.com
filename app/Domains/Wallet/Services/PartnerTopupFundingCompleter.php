<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Models\RailMirrorEvent;
use App\Models\StripeTransaction;

class PartnerTopupFundingCompleter
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected RailMirrorService $railMirror,
        protected TopupImmediateNotifier $topupImmediateNotifier,
    ) {}

    public function complete(StripeTransaction $transaction): void
    {
        $transaction = $transaction->fresh();

        if ($transaction->partner_funding_status === PartnerFundingStatus::Completed) {
            return;
        }

        $netAmount = max(0, (int) ($transaction->wallester_funding_net_amount ?? $transaction->net_amount));

        if ($netAmount > 0 && ! $this->ledgerService->hasReleasedPartnerFunding($transaction)) {
            $this->ledgerService->releasePendingToAvailable(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                amount: $netAmount,
                reference: $transaction,
                description: 'Wallester top-up funding released #'.$transaction->id,
            );
        }

        $this->mirrorWiseAllocation($transaction, $netAmount);

        $updates = [
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funding_failure_reason' => null,
            'partner_funded_at' => now(),
        ];

        if ((int) ($transaction->stripe_deferred_net_amount ?? 0) <= 0) {
            $updates['balance_settled_at'] = $transaction->balance_settled_at ?? now();
        }

        $transaction->update($updates);

        $this->topupImmediateNotifier->notifyIfEligible($transaction->fresh());
    }

    private function mirrorWiseAllocation(StripeTransaction $transaction, int $netAmount): void
    {
        if ($netAmount <= 0) {
            return;
        }

        foreach ([
            'stripe_payout_wise:'.$transaction->id,
            'stripe_liquidity_wise:'.$transaction->id,
            'stripe_topup_wise:'.$transaction->id,
        ] as $key) {
            if (RailMirrorEvent::query()->where('idempotency_key', $key)->exists()) {
                return;
            }
        }

        $this->railMirror->creditAllocation(
            customerId: (int) $transaction->customer_id,
            currency: (string) $transaction->currency,
            amountCents: $netAmount,
            idempotencyKey: 'stripe_topup_wise:'.$transaction->id,
            reason: 'wallester_topup_available',
            reference: $transaction,
        );
    }
}

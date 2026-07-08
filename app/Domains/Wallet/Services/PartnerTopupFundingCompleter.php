<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Models\StripeTransaction;

class PartnerTopupFundingCompleter
{
    public function __construct(
        protected LedgerService $ledgerService,
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

        $updates = [
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funding_failure_reason' => null,
            'partner_funded_at' => now(),
        ];

        if ((int) ($transaction->stripe_deferred_net_amount ?? 0) <= 0) {
            $updates['balance_settled_at'] = $transaction->balance_settled_at ?? now();
        }

        $transaction->update($updates);
    }
}

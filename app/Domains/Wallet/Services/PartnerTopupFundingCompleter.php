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

        $netAmount = (int) $transaction->net_amount;

        if ($netAmount > 0 && ! $this->ledgerService->hasReleasedPartnerFunding($transaction)) {
            $this->ledgerService->releasePendingToAvailable(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                amount: $netAmount,
                reference: $transaction,
                description: 'Wallester top-up funding released #'.$transaction->id,
            );
        }

        $transaction->update([
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funding_failure_reason' => null,
            'partner_funded_at' => now(),
        ]);
    }
}

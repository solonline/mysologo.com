<?php

namespace App\Domains\Wallester\Jobs;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Models\StripeTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FundWallesterTopupFromStripeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $stripeTransactionId,
    ) {
        $this->onQueue('wallester');
    }

    public function handle(
        FundingPartnerMovementService $movements,
        TopupWallesterFundingPolicy $fundingPolicy,
    ): void {
        $transaction = StripeTransaction::query()->find($this->stripeTransactionId);

        if ($transaction === null) {
            return;
        }

        if ($fundingPolicy->isBeforeAccountingCutoff($transaction)) {
            return;
        }

        $amountCents = max(0, (int) ($transaction->wallester_funding_net_amount ?? 0));

        if ($amountCents <= 0) {
            return;
        }

        $idempotencyKey = 'stripe_topup:'.$transaction->id;

        if ($movements->hasCompletedMovement($idempotencyKey)) {
            return;
        }

        if ($transaction->partner_funding_status !== PartnerFundingStatus::Completed) {
            $transaction->update([
                'partner_funding_status' => PartnerFundingStatus::Pending,
                'partner_funding_failure_reason' => null,
            ]);
        }

        try {
            $movements->retryTransferFromMasterToCustomer(
                to: $transaction->customer,
                amountCents: $amountCents,
                currency: $transaction->currency,
                description: 'Stripe top-up #'.$transaction->id,
                idempotencyKey: $idempotencyKey,
                reference: $transaction,
            );
        } catch (Throwable $exception) {
            Log::channel('wallester')->error('wallester_topup_from_stripe_failed', [
                'transaction_id' => $transaction->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $transaction = StripeTransaction::query()->find($this->stripeTransactionId);

        if ($transaction === null) {
            return;
        }

        $status = $transaction->partner_funding_status;

        if ($status === PartnerFundingStatus::Completed || $status === PartnerFundingStatus::NotRequired) {
            return;
        }

        if ($status === PartnerFundingStatus::AwaitingLiquidity) {
            return;
        }

        $transaction->update([
            'partner_funding_status' => PartnerFundingStatus::Failed,
            'partner_funding_failure_reason' => $exception?->getMessage(),
        ]);
    }
}

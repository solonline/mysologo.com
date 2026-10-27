<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;
use Illuminate\Support\Carbon;

class BankTransferLiquidityService
{
    public function __construct(
        protected LedgerService $ledger,
        protected RailMirrorService $railMirror,
        protected TopupWallesterFundingPolicy $fundingPolicy,
    ) {}

    public function releaseDue(Carbon $date): int
    {
        $released = 0;

        StripeTransaction::query()
            ->where('status', 'succeeded')
            ->where(function ($query): void {
                $query->where('type', TransactionType::BANK_TRANSFER->value)
                    ->orWhere(function ($bankTransfer): void {
                        $bankTransfer->where('payment_method_type', 'bank_transfer')
                            ->whereNotNull('stripe_cash_balance_transaction_id');
                    });
            })
            ->whereNotNull('available_on')
            ->whereDate('available_on', '<=', $date->toDateString())
            ->whereNull('balance_settled_at')
            ->where('net_amount', '>', 0)
            ->orderBy('id')
            ->each(function (StripeTransaction $transaction) use (&$released): void {
                if ($this->release($transaction)) {
                    $released++;
                }
            });

        return $released;
    }

    public function release(StripeTransaction $transaction): bool
    {
        if (! $this->isBankTransfer($transaction) || $transaction->balance_settled_at !== null) {
            return false;
        }

        if (
            $transaction->partner_funding_status !== null
            && $transaction->partner_funding_status !== PartnerFundingStatus::NotRequired
        ) {
            return false;
        }

        $netAmount = (int) $transaction->net_amount;

        if ($netAmount <= 0 || ! $this->liquidityDateReached($transaction)) {
            return false;
        }

        $missing = $netAmount - $this->ledger->pendingHeldFor($transaction);

        if ($missing > 0) {
            $this->ledger->recordPendingCredit(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                entryType: LedgerEntryType::PAYMENT_RECEIVED,
                amount: $missing,
                reference: $transaction,
                description: 'Stripe payment #'.$transaction->id,
            );
        }

        $this->railMirror->creditAllocation(
            customerId: (int) $transaction->customer_id,
            currency: (string) $transaction->currency,
            amountCents: $netAmount,
            idempotencyKey: 'stripe_liquidity_wise:'.$transaction->id,
            reason: 'bank_transfer_liquidity',
            reference: $transaction,
        );

        if ($this->fundingPolicy->hasCompletedMovement($transaction) || $this->fundingPolicy->hasPendingMovement($transaction)) {
            return true;
        }

        $transaction->update([
            'immediate_gross_amount' => 0,
            'wallester_funding_net_amount' => $netAmount,
            'stripe_deferred_net_amount' => 0,
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'partner_funding_failure_reason' => null,
        ]);

        FundWallesterTopupFromStripeJob::dispatch($transaction->id);

        return true;
    }

    private function isBankTransfer(StripeTransaction $transaction): bool
    {
        return $transaction->type === TransactionType::BANK_TRANSFER
            || (
                $transaction->payment_method_type === 'bank_transfer'
                && filled($transaction->stripe_cash_balance_transaction_id)
            );
    }

    private function liquidityDateReached(StripeTransaction $transaction): bool
    {
        $availableOn = $transaction->available_on?->timezone(config('app.timezone'))->startOfDay();

        if ($availableOn === null) {
            return false;
        }

        return ! $availableOn->isAfter(now()->timezone(config('app.timezone'))->startOfDay());
    }
}

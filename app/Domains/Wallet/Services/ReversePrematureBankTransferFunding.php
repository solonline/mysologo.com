<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use App\Models\RailMirrorEvent;
use App\Models\StripePaymentIntent;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReversePrematureBankTransferFunding
{
    public function __construct(
        protected WallesterFundingTransferDriver $wallester,
        protected LedgerService $ledger,
        protected RailMirrorService $railMirror,
    ) {}

    public function reverse(StripeTransaction $transaction): bool
    {
        $transaction = $transaction->fresh() ?? $transaction;
        $netAmount = max(0, (int) ($transaction->wallester_funding_net_amount ?: $transaction->net_amount));

        if ($netAmount <= 0 || $transaction->partner_funding_status !== PartnerFundingStatus::Completed) {
            return false;
        }

        $customer = $transaction->customer;

        if ($customer === null) {
            return false;
        }

        $fromAccount = $this->wallester->requireActiveAccount($customer, (string) $transaction->currency);
        $masterAccount = $this->wallester->getPlatformMasterAccount((string) $transaction->currency);

        $this->wallester->executeTransfer(
            fromAccount: $fromAccount,
            toAccount: $masterAccount,
            amountCents: $netAmount,
            description: 'Return to main',
            auditUserId: 'platform',
        );

        DB::transaction(function () use ($transaction, $netAmount): void {
            FundingPartnerMovement::query()
                ->where(function ($query) use ($transaction): void {
                    $query->where('idempotency_key', 'like', 'stripe_topup:'.$transaction->id.':%')
                        ->orWhere('idempotency_key', 'stripe_topup:'.$transaction->id);
                })
                ->delete();

            $this->unwindLedger($transaction, $netAmount);
            $this->unwindWise($transaction, $netAmount);

            $transaction->update([
                'type' => TransactionType::BANK_TRANSFER,
                'payment_method_type' => 'bank_transfer',
                'balance_settled_at' => null,
                'partner_funding_status' => PartnerFundingStatus::NotRequired,
                'partner_funding_failure_reason' => null,
                'partner_funded_at' => null,
                'immediate_gross_amount' => 0,
                'wallester_funding_net_amount' => 0,
                'stripe_deferred_net_amount' => $netAmount,
            ]);

            StripePaymentIntent::query()
                ->where(function ($query) use ($transaction): void {
                    $query->where('stripe_transaction_id', $transaction->id);

                    if (filled($transaction->stripe_payment_intent_id)) {
                        $query->orWhere('stripe_payment_intent_id', $transaction->stripe_payment_intent_id);
                    }
                })
                ->update(['type' => TransactionType::BANK_TRANSFER->value]);
        });

        Log::channel('wallester')->info('premature_bank_transfer_funding_returned', [
            'transaction_id' => $transaction->id,
            'amount_cents' => $netAmount,
        ]);

        return true;
    }

    private function unwindLedger(StripeTransaction $transaction, int $netAmount): void
    {
        if ($this->ledger->hasReleasedPartnerFunding($transaction)) {
            $this->ledger->record(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                entryType: LedgerEntryType::MANUAL_DEBIT,
                amount: $netAmount,
                reference: $transaction,
                description: 'Stripe bank transfer funding unwind #'.$transaction->id,
            );

            return;
        }

        $hasPendingCredit = LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where('description', 'Stripe payment #'.$transaction->id)
            ->exists();

        if ($hasPendingCredit) {
            $this->ledger->reversePendingCredit(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                amount: $netAmount,
                reference: $transaction,
                description: 'Stripe bank transfer funding unwind #'.$transaction->id,
            );
        }
    }

    private function unwindWise(StripeTransaction $transaction, int $netAmount): void
    {
        foreach (['stripe_payout_wise:'.$transaction->id, 'stripe_liquidity_wise:'.$transaction->id] as $key) {
            if (! RailMirrorEvent::query()->where('idempotency_key', $key)->exists()) {
                continue;
            }

            $this->railMirror->debitAllocation(
                customerId: (int) $transaction->customer_id,
                currency: (string) $transaction->currency,
                amountCents: $netAmount,
                idempotencyKey: $key.':unwind',
                reason: 'bank_transfer_liquidity_unwind',
                reference: $transaction,
                allowNegative: true,
            );
        }
    }
}

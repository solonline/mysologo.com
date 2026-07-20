<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentWalletService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected TopupWallesterFundingPolicy $topupFundingPolicy,
    ) {}

    public function creditFromStripePayment(StripeTransaction $transaction): bool
    {
        if ($transaction->balance_settled_at !== null) {
            return false;
        }

        if ($transaction->type === TransactionType::MERCHANT_PAYMENT) {
            return $this->creditMerchantPaymentAsPending($transaction);
        }

        $netAmount = (int) $transaction->net_amount;
        $platformFee = (int) $transaction->platform_fee;
        $wallesterFundingNet = $this->topupFundingPolicy->wallesterFundingNetAmount($transaction);
        $stripeDeferredNet = $this->topupFundingPolicy->stripeDeferredNetAmount($transaction);
        $walletCreditNet = $transaction->type === TransactionType::TOPUP
            ? $wallesterFundingNet
            : $netAmount;

        if ($netAmount <= 0 && $platformFee <= 0) {
            $transaction->update(['balance_settled_at' => now()]);

            return false;
        }

        if ($walletCreditNet > 0 && $this->hasImmediateWalletLedgerEntry($transaction)) {
            if ($stripeDeferredNet <= 0) {
                $transaction->update(['balance_settled_at' => now()]);
            }

            return false;
        }

        if ($walletCreditNet <= 0 && $stripeDeferredNet <= 0 && $this->hasImmediateWalletLedgerEntry($transaction)) {
            $transaction->update(['balance_settled_at' => now()]);

            return false;
        }

        DB::transaction(function () use (
            $transaction,
            $walletCreditNet,
            $platformFee,
            $wallesterFundingNet,
            $stripeDeferredNet,
        ): void {
            $requiresPartnerFunding = $wallesterFundingNet > 0
                && $this->topupFundingPolicy->hasOperationalWallesterAccount($transaction);

            if ($walletCreditNet > 0) {
                if ($requiresPartnerFunding) {
                    $this->ledgerService->recordPendingCredit(
                        customerId: $transaction->customer_id,
                        currency: $transaction->currency,
                        entryType: LedgerEntryType::PAYMENT_RECEIVED,
                        amount: $walletCreditNet,
                        reference: $transaction,
                        description: 'Stripe payment #'.$transaction->id,
                    );
                } else {
                    $this->ledgerService->record(
                        customerId: $transaction->customer_id,
                        currency: $transaction->currency,
                        entryType: LedgerEntryType::PAYMENT_RECEIVED,
                        amount: $walletCreditNet,
                        reference: $transaction,
                        description: 'Stripe payment #'.$transaction->id,
                    );
                }
            }

            if ($platformFee > 0) {
                $platformCustomer = $this->resolvePlatformCustomer();

                if ($platformCustomer !== null) {
                    $this->ledgerService->record(
                        customerId: $platformCustomer->id,
                        currency: $transaction->currency,
                        entryType: LedgerEntryType::MANUAL_CREDIT,
                        amount: $platformFee,
                        reference: $transaction,
                        description: 'Platform fee from transaction #'.$transaction->id,
                    );
                } else {
                    Log::channel('stripe')->warning('stripe_payment_wallet: platform customer missing for fee', [
                        'transaction_id' => $transaction->id,
                        'platform_fee' => $platformFee,
                    ]);
                }
            }

            $transaction->update([
                'balance_settled_at' => $stripeDeferredNet > 0 ? null : now(),
                'partner_funding_status' => $requiresPartnerFunding
                    ? PartnerFundingStatus::Pending
                    : PartnerFundingStatus::NotRequired,
            ]);
        });

        return true;
    }

    private function creditMerchantPaymentAsPending(StripeTransaction $transaction): bool
    {
        $netAmount = (int) $transaction->net_amount;
        $platformFee = (int) $transaction->platform_fee;

        if ($netAmount <= 0 && $platformFee <= 0) {
            return false;
        }

        if ($netAmount > 0 && $this->hasImmediateWalletLedgerEntry($transaction)) {
            $transaction->update([
                'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
            ]);

            return false;
        }

        DB::transaction(function () use ($transaction, $netAmount, $platformFee): void {
            if ($netAmount > 0) {
                $this->ledgerService->recordPendingCredit(
                    customerId: $transaction->customer_id,
                    currency: $transaction->currency,
                    entryType: LedgerEntryType::PAYMENT_RECEIVED,
                    amount: $netAmount,
                    reference: $transaction,
                    description: 'Stripe payment #'.$transaction->id,
                    metadata: [
                        'settlement' => 'awaiting_stripe_payout',
                        'source' => 'merchant_payment',
                    ],
                );
            }

            if ($platformFee > 0) {
                $platformCustomer = $this->resolvePlatformCustomer();

                if ($platformCustomer !== null) {
                    $this->ledgerService->record(
                        customerId: $platformCustomer->id,
                        currency: $transaction->currency,
                        entryType: LedgerEntryType::MANUAL_CREDIT,
                        amount: $platformFee,
                        reference: $transaction,
                        description: 'Platform fee from transaction #'.$transaction->id,
                    );
                }
            }

            $transaction->update([
                'balance_settled_at' => null,
                'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
                'wallester_funding_net_amount' => 0,
                'stripe_deferred_net_amount' => $netAmount,
                'immediate_gross_amount' => 0,
            ]);
        });

        return true;
    }

    public function creditDeferredStripeSettlement(StripeTransaction $transaction): bool
    {
        if ($transaction->type !== TransactionType::TOPUP) {
            return false;
        }

        $deferredNet = $this->topupFundingPolicy->stripeDeferredNetAmount($transaction);

        if ($deferredNet <= 0) {
            return false;
        }

        if ($this->hasDeferredSettlementLedgerEntry($transaction)) {
            if ($transaction->balance_settled_at === null) {
                $transaction->update(['balance_settled_at' => now()]);
            }

            return false;
        }

        DB::transaction(function () use ($transaction, $deferredNet): void {
            $this->ledgerService->record(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                entryType: LedgerEntryType::PAYMENT_RECEIVED,
                amount: $deferredNet,
                reference: $transaction,
                description: 'Stripe deferred settlement #'.$transaction->id,
            );

            $transaction->update(['balance_settled_at' => now()]);
        });

        return true;
    }

    public function reverseStripePayment(StripeTransaction $transaction): void
    {
        if ($transaction->balance_settled_at === null && ! $this->hasImmediateWalletLedgerEntry($transaction)) {
            return;
        }

        $netAmount = (int) $transaction->net_amount;

        if ($netAmount <= 0) {
            $transaction->update(['balance_settled_at' => null]);

            return;
        }

        if ($this->hasPaymentReversalLedgerEntry($transaction)) {
            return;
        }

        DB::transaction(function () use ($transaction, $netAmount): void {
            $this->ledgerService->record(
                customerId: $transaction->customer_id,
                currency: $transaction->currency,
                entryType: LedgerEntryType::MANUAL_DEBIT,
                amount: $netAmount,
                reference: $transaction,
                description: 'Stripe payment reversal #'.$transaction->id,
            );

            $transaction->update(['balance_settled_at' => null]);
        });
    }

    private function hasImmediateWalletLedgerEntry(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where('description', 'Stripe payment #'.$transaction->id)
            ->exists();
    }

    private function hasDeferredSettlementLedgerEntry(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_RECEIVED)
            ->where('description', 'Stripe deferred settlement #'.$transaction->id)
            ->exists();
    }

    private function hasPaymentReversalLedgerEntry(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::MANUAL_DEBIT)
            ->where('description', 'Stripe payment reversal #'.$transaction->id)
            ->exists();
    }

    private function resolvePlatformCustomer(): ?Customer
    {
        return Cache::rememberForever('platform_customer', fn () => Customer::query()
            ->where('type', 'platform')
            ->first());
    }
}

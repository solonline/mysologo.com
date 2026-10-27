<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Jobs\ProcessStripePayoutPaidJob;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerBalance;
use App\Models\RailMirrorEvent;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\Log;

class StripePayoutSettlementService
{
    private bool $dispatchPartnerFunding = true;

    public function __construct(
        protected StripeGatewayInterface $stripeGateway,
        protected StripePaymentWalletService $stripePaymentWallet,
        protected TopupWallesterFundingPolicy $topupFundingPolicy,
        protected LedgerService $ledger,
        protected RailMirrorService $railMirror,
    ) {}

    /**
     * Liquidates local transactions confirmed by a Stripe payout.paid event.
     *
     * @return array{matched: int, deferred_credited: int, wallester_dispatched: int, missing: int, skipped: int}
     */
    public function settlePayout(string $payoutId, string $currency = 'eur', bool $livemode = true, bool $dispatchPartnerFunding = true): array
    {
        $this->dispatchPartnerFunding = $dispatchPartnerFunding;
        $mode = $livemode ? 'live' : 'test';
        $result = $this->stripeGateway->getTransactionsByIdPayout($payoutId, strtolower($currency), $mode);

        if (($result['status'] ?? null) !== 'success') {
            Log::channel('stripe')->error('stripe_payout_settlement: failed to list balance transactions', [
                'payout_id' => $payoutId,
                'message' => $result['message'] ?? null,
            ]);

            throw new \RuntimeException($result['message'] ?? 'Failed to list payout balance transactions.');
        }

        $stats = [
            'matched' => 0,
            'deferred_credited' => 0,
            'wallester_dispatched' => 0,
            'missing' => 0,
            'skipped' => 0,
        ];

        $charges = collect($result['data']['data'] ?? [])
            ->filter(fn (array $bt): bool => ($bt['type'] ?? null) === 'charge')
            ->values();

        foreach ($charges as $balanceTransaction) {
            $chargeId = $balanceTransaction['source'] ?? null;

            if (! is_string($chargeId) || ! str_starts_with($chargeId, 'ch_')) {
                $stats['skipped']++;

                continue;
            }

            $transaction = StripeTransaction::query()
                ->where('stripe_charge_id', $chargeId)
                ->whereNull('related_transaction_id')
                ->first();

            if ($transaction === null) {
                $stats['missing']++;
                Log::channel('stripe')->warning('stripe_payout_settlement: charge not found in stripe_transactions', [
                    'payout_id' => $payoutId,
                    'charge_id' => $chargeId,
                    'balance_transaction_id' => $balanceTransaction['id'] ?? null,
                ]);

                continue;
            }

            $stats['matched']++;
            $outcome = $this->settleTransaction($transaction->fresh(), $payoutId);
            $stats['deferred_credited'] += $outcome['deferred_credited'] ? 1 : 0;
            $stats['wallester_dispatched'] += $outcome['wallester_dispatched'] ? 1 : 0;
            $stats['skipped'] += $outcome['skipped'] ? 1 : 0;
        }

        Log::channel('stripe')->info('stripe_payout_settlement: completed', [
            'payout_id' => $payoutId,
            'currency' => $currency,
            'livemode' => $livemode,
            ...$stats,
        ]);

        return $stats;
    }

    /**
     * Re-dispatch settlement for recently paid Stripe payouts (missed webhooks / old workers).
     *
     * @return array{dispatched: int, live: int, test: int}
     */
    public function recoverRecentPaidPayouts(int $createdGte): array
    {
        $stats = ['dispatched' => 0, 'live' => 0, 'test' => 0];

        foreach ([true, false] as $livemode) {
            $modeKey = $livemode ? 'live' : 'test';
            $result = $this->stripeGateway->listPayouts($createdGte, 'eur', $modeKey);

            if (($result['status'] ?? null) !== 'success') {
                Log::channel('stripe')->warning('stripe_payout_settlement: failed to list payouts for recovery', [
                    'mode' => $modeKey,
                    'message' => $result['message'] ?? null,
                ]);

                continue;
            }

            foreach ($result['data']['data'] ?? [] as $payout) {
                if (! is_array($payout)) {
                    continue;
                }

                $payoutId = $payout['id'] ?? null;

                if (! is_string($payoutId) || ! str_starts_with($payoutId, 'po_')) {
                    continue;
                }

                if (($payout['status'] ?? null) !== 'paid') {
                    continue;
                }

                ProcessStripePayoutPaidJob::dispatch(
                    $payoutId,
                    strtolower((string) ($payout['currency'] ?? 'eur')),
                    $livemode,
                );

                $stats['dispatched']++;
                $stats[$modeKey]++;
            }
        }

        Log::channel('stripe')->info('stripe_payout_settlement: recovery dispatch completed', $stats);

        return $stats;
    }

    /**
     * @return array{deferred_credited: bool, wallester_dispatched: bool, skipped: bool}
     */
    public function settleTransaction(StripeTransaction $transaction, string $payoutId): array
    {
        if ($transaction->type === TransactionType::MERCHANT_PAYMENT) {
            return $this->settleMerchantPayment($transaction, $payoutId);
        }

        if ($transaction->type === TransactionType::TOPUP) {
            if (
                $transaction->payment_method_type === 'bank_transfer'
                || filled($transaction->stripe_cash_balance_transaction_id)
            ) {
                Log::channel('stripe')->info('stripe_payout_settlement: IBAN top-up waits for available_on', [
                    'payout_id' => $payoutId,
                    'transaction_id' => $transaction->id,
                    'available_on' => $transaction->available_on?->toDateString(),
                ]);

                return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
            }

            return $this->settleTopup($transaction, $payoutId);
        }

        if ($transaction->type === TransactionType::BANK_TRANSFER) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        Log::channel('stripe')->info('stripe_payout_settlement: unsupported transaction type skipped', [
            'payout_id' => $payoutId,
            'transaction_id' => $transaction->id,
            'type' => $transaction->type?->value ?? $transaction->getAttributes()['type'] ?? null,
        ]);

        return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
    }

    /**
     * @return array{deferred_credited: bool, wallester_dispatched: bool, skipped: bool}
     */
    private function settleMerchantPayment(StripeTransaction $transaction, string $payoutId): array
    {
        $fundingNet = max(0, (int) $transaction->net_amount);

        if ($fundingNet <= 0) {
            $transaction->update([
                'partner_funding_status' => PartnerFundingStatus::NotRequired,
                'balance_settled_at' => $transaction->balance_settled_at ?? now(),
                'wallester_funding_net_amount' => 0,
                'stripe_deferred_net_amount' => 0,
            ]);

            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        $released = $this->releasePendingFunding($transaction, $fundingNet);
        $wiseCredited = $this->creditWiseAllocation($transaction, $fundingNet);
        $wallesterDispatched = $this->dispatchWallesterFunding($transaction->fresh(), $fundingNet, $payoutId, 'merchant');

        return [
            'deferred_credited' => false,
            'wallester_dispatched' => $wallesterDispatched,
            'skipped' => ! $released && ! $wiseCredited && ! $wallesterDispatched,
        ];
    }

    /**
     * @return array{deferred_credited: bool, wallester_dispatched: bool, skipped: bool}
     */
    private function settleTopup(StripeTransaction $transaction, string $payoutId): array
    {
        $fundingNet = max(0, (int) $transaction->net_amount);

        if ($fundingNet <= 0) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        $deferredCredited = false;

        if ((int) ($transaction->stripe_deferred_net_amount ?? 0) > 0 && $transaction->balance_settled_at === null) {
            $deferredCredited = $this->stripePaymentWallet->creditDeferredStripeSettlement($transaction->fresh());
        }

        $released = $this->releasePendingFunding($transaction->fresh(), $fundingNet);
        $wiseCredited = $this->creditWiseAllocation($transaction->fresh(), $fundingNet);
        $wallesterDispatched = $this->dispatchWallesterFunding($transaction->fresh(), $fundingNet, $payoutId, 'topup');

        return [
            'deferred_credited' => $deferredCredited,
            'wallester_dispatched' => $wallesterDispatched,
            'skipped' => ! $deferredCredited && ! $released && ! $wiseCredited && ! $wallesterDispatched,
        ];
    }

    private function releasePendingFunding(StripeTransaction $transaction, int $netAmount): bool
    {
        if ($netAmount <= 0 || $this->ledger->hasReleasedPartnerFunding($transaction)) {
            return false;
        }

        $pending = (int) CustomerBalance::query()
            ->where('customer_id', $transaction->customer_id)
            ->where('currency', strtoupper((string) $transaction->currency))
            ->value('pending');

        if ($pending < $netAmount) {
            return false;
        }

        $this->ledger->releasePendingToAvailable(
            customerId: $transaction->customer_id,
            currency: $transaction->currency,
            amount: $netAmount,
            reference: $transaction,
            description: 'Stripe payout funding released #'.$transaction->id,
        );

        return true;
    }

    private function creditWiseAllocation(StripeTransaction $transaction, int $netAmount): bool
    {
        if ($netAmount <= 0 || $this->wiseAlreadyMirrored($transaction)) {
            return false;
        }

        return $this->railMirror->creditAllocation(
            customerId: (int) $transaction->customer_id,
            currency: (string) $transaction->currency,
            amountCents: $netAmount,
            idempotencyKey: 'stripe_payout_wise:'.$transaction->id,
            reason: 'stripe_payout',
            reference: $transaction,
        );
    }

    private function dispatchWallesterFunding(
        StripeTransaction $transaction,
        int $netAmount,
        string $payoutId,
        string $kind,
    ): bool {
        if (! $this->dispatchPartnerFunding || $this->wallesterAlreadyCovered($transaction, $netAmount)) {
            return false;
        }

        $transaction->update([
            'wallester_funding_net_amount' => $netAmount,
            'stripe_deferred_net_amount' => 0,
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'partner_funding_failure_reason' => null,
        ]);

        FundWallesterTopupFromStripeJob::dispatch($transaction->id);

        Log::channel('stripe')->info('stripe_payout_settlement: '.$kind.' armed for Wallester funding', [
            'payout_id' => $payoutId,
            'transaction_id' => $transaction->id,
            'wallester_funding_net_amount' => $netAmount,
        ]);

        return true;
    }

    private function wiseAlreadyMirrored(StripeTransaction $transaction): bool
    {
        foreach ([
            'stripe_payout_wise:'.$transaction->id,
            'stripe_liquidity_wise:'.$transaction->id,
            'stripe_topup_wise:'.$transaction->id,
        ] as $key) {
            if (RailMirrorEvent::query()->where('idempotency_key', $key)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function wallesterAlreadyCovered(StripeTransaction $transaction, int $netAmount): bool
    {
        if ($netAmount <= 0) {
            return true;
        }

        if ($this->topupFundingPolicy->hasCompletedMovement($transaction) || $this->topupFundingPolicy->hasPendingMovement($transaction)) {
            return true;
        }

        $status = $transaction->partner_funding_status;

        if ($status === PartnerFundingStatus::Completed) {
            return true;
        }

        $deferredNet = (int) ($transaction->stripe_deferred_net_amount ?? 0);
        $wallesterNet = (int) ($transaction->wallester_funding_net_amount ?? 0);
        $fullyAssigned = $deferredNet === 0 && $wallesterNet >= $netAmount;

        if ($status === PartnerFundingStatus::NotRequired && $deferredNet === 0) {
            return true;
        }

        return $fullyAssigned && (
            $status === PartnerFundingStatus::Pending
            || (int) ($transaction->immediate_gross_amount ?? 0) > 0
        );
    }
}

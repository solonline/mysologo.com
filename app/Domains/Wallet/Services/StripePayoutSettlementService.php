<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\Log;

class StripePayoutSettlementService
{
    public function __construct(
        protected StripeGatewayInterface $stripeGateway,
        protected StripePaymentWalletService $stripePaymentWallet,
        protected TopupWallesterFundingPolicy $topupFundingPolicy,
    ) {}

    /**
     * Liquidates local transactions confirmed by a Stripe payout.paid event.
     *
     * @return array{matched: int, deferred_credited: int, wallester_dispatched: int, missing: int, skipped: int}
     */
    public function settlePayout(string $payoutId, string $currency = 'eur', bool $livemode = true): array
    {
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
     * @return array{deferred_credited: bool, wallester_dispatched: bool, skipped: bool}
     */
    public function settleTransaction(StripeTransaction $transaction, string $payoutId): array
    {
        if ($transaction->type === TransactionType::MERCHANT_PAYMENT) {
            return $this->settleMerchantPayment($transaction, $payoutId);
        }

        if ($transaction->type === TransactionType::TOPUP) {
            return $this->settleTopup($transaction, $payoutId);
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
        $status = $transaction->partner_funding_status;

        if ($status === PartnerFundingStatus::Completed || $status === PartnerFundingStatus::NotRequired) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        if ($this->topupFundingPolicy->hasCompletedMovement($transaction)) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

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

        $transaction->update([
            'wallester_funding_net_amount' => $fundingNet,
            'stripe_deferred_net_amount' => 0,
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'partner_funding_failure_reason' => null,
        ]);

        if ($this->topupFundingPolicy->hasPendingMovement($transaction->fresh())) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        FundWallesterTopupFromStripeJob::dispatch($transaction->id);

        Log::channel('stripe')->info('stripe_payout_settlement: merchant armed for Wallester funding', [
            'payout_id' => $payoutId,
            'transaction_id' => $transaction->id,
            'wallester_funding_net_amount' => $fundingNet,
        ]);

        return ['deferred_credited' => false, 'wallester_dispatched' => true, 'skipped' => false];
    }

    /**
     * @return array{deferred_credited: bool, wallester_dispatched: bool, skipped: bool}
     */
    private function settleTopup(StripeTransaction $transaction, string $payoutId): array
    {
        $deferredCredited = false;
        $wallesterDispatched = false;

        if ((int) ($transaction->stripe_deferred_net_amount ?? 0) > 0 && $transaction->balance_settled_at === null) {
            $deferredCredited = $this->stripePaymentWallet->creditDeferredStripeSettlement($transaction->fresh());
        }

        $fresh = $transaction->fresh();

        if (
            $fresh
            && $this->topupFundingPolicy->requiresPartnerFunding($fresh)
            && $this->topupFundingPolicy->isRetriable($fresh)
            && ! $this->topupFundingPolicy->hasPendingMovement($fresh)
        ) {
            FundWallesterTopupFromStripeJob::dispatch($fresh->id);
            $wallesterDispatched = true;

            Log::channel('stripe')->info('stripe_payout_settlement: topup Wallester funding dispatched', [
                'payout_id' => $payoutId,
                'transaction_id' => $fresh->id,
            ]);
        }

        if (! $deferredCredited && ! $wallesterDispatched) {
            return ['deferred_credited' => false, 'wallester_dispatched' => false, 'skipped' => true];
        }

        return [
            'deferred_credited' => $deferredCredited,
            'wallester_dispatched' => $wallesterDispatched,
            'skipped' => false,
        ];
    }
}

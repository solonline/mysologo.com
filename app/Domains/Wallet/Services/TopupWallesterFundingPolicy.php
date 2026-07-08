<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;

class TopupWallesterFundingPolicy
{
    public const DAILY_TOPUP_LIMIT_CENTS = 10_000;

    public function requiresPartnerFunding(StripeTransaction $transaction): bool
    {
        if ($transaction->type !== TransactionType::TOPUP) {
            return false;
        }

        if ((int) $transaction->amount > self::DAILY_TOPUP_LIMIT_CENTS) {
            return false;
        }

        $wallesterAccount = $transaction->customer?->wallesterAccountFor($transaction->currency);

        return $wallesterAccount !== null && $wallesterAccount->status->isOperational();
    }

    public function shouldDispatchPartnerFundingJob(StripeTransaction $transaction, array $charge): bool
    {
        if (! $this->requiresPartnerFunding($transaction)) {
            return false;
        }

        if (($charge['status'] ?? null) !== 'succeeded') {
            return false;
        }

        if (array_key_exists('paid', $charge) && $charge['paid'] !== true) {
            return false;
        }

        $status = $transaction->partner_funding_status;

        if ($status instanceof PartnerFundingStatus) {
            if ($status->isTerminal()) {
                return false;
            }
        }

        if ($this->hasCompletedMovement($transaction)) {
            return false;
        }

        if ($this->hasPendingMovement($transaction)) {
            return false;
        }

        return true;
    }

    public function isRetriable(StripeTransaction $transaction): bool
    {
        if (! $this->requiresPartnerFunding($transaction)) {
            return false;
        }

        $status = $transaction->partner_funding_status;

        if ($status instanceof PartnerFundingStatus && $status === PartnerFundingStatus::Completed) {
            return false;
        }

        return ! $this->hasCompletedMovement($transaction);
    }

    public function hasCompletedMovement(StripeTransaction $transaction): bool
    {
        return FundingPartnerMovement::query()
            ->where('idempotency_key', $this->movementKey($transaction).':out')
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->exists();
    }

    public function hasPendingMovement(StripeTransaction $transaction): bool
    {
        return FundingPartnerMovement::query()
            ->where('idempotency_key', $this->movementKey($transaction).':out')
            ->where('status', FundingPartnerMovementStatus::Pending)
            ->exists();
    }

    public function movementKey(StripeTransaction $transaction): string
    {
        return 'stripe_topup:'.$transaction->id;
    }
}

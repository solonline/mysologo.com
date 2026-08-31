<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\DTOs\TopupSettlementAllocation;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Support\Carbon;

class TopupWallesterFundingPolicy
{
    public const DAILY_IMMEDIATE_TOPUP_GROSS_LIMIT_CENTS = 10_000;

    public const ACCOUNTING_CUTOFF_AT = '2026-08-17 00:00:00';

    public function allocate(StripeTransaction $transaction): TopupSettlementAllocation
    {
        $grossAmount = (int) $transaction->amount;
        $netAmount = max(0, (int) $transaction->net_amount);

        if ($transaction->type !== TransactionType::TOPUP || $grossAmount <= 0) {
            return new TopupSettlementAllocation(0, 0, $netAmount);
        }

        $remainingQuota = max(
            0,
            self::DAILY_IMMEDIATE_TOPUP_GROSS_LIMIT_CENTS - $this->dailyImmediateGrossUsedCents($transaction),
        );

        if ($grossAmount <= $remainingQuota && $this->hasOperationalWallesterAccount($transaction)) {
            return new TopupSettlementAllocation(
                immediateGrossCents: $grossAmount,
                wallesterFundingNetCents: $netAmount,
                stripeDeferredNetCents: 0,
            );
        }

        return new TopupSettlementAllocation(
            immediateGrossCents: 0,
            wallesterFundingNetCents: 0,
            stripeDeferredNetCents: $netAmount,
        );
    }

    public function requiresPartnerFunding(StripeTransaction $transaction): bool
    {
        return $this->wallesterFundingNetAmount($transaction) > 0;
    }

    public function wallesterFundingNetAmount(StripeTransaction $transaction): int
    {
        if ($transaction->wallester_funding_net_amount !== null) {
            return max(0, (int) $transaction->wallester_funding_net_amount);
        }

        return $this->allocate($transaction)->wallesterFundingNetCents;
    }

    public function stripeDeferredNetAmount(StripeTransaction $transaction): int
    {
        if ($transaction->stripe_deferred_net_amount !== null) {
            return max(0, (int) $transaction->stripe_deferred_net_amount);
        }

        return $this->allocate($transaction)->stripeDeferredNetCents;
    }

    public function shouldDispatchPartnerFundingJob(StripeTransaction $transaction, array $charge): bool
    {
        if ($this->isBeforeAccountingCutoff($transaction)) {
            return false;
        }

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
        if ($this->isBeforeAccountingCutoff($transaction)) {
            return false;
        }

        if (! $this->requiresPartnerFunding($transaction)) {
            return false;
        }

        $status = $transaction->partner_funding_status;

        if ($status instanceof PartnerFundingStatus && $status->isTerminal()) {
            return false;
        }

        return ! $this->hasCompletedMovement($transaction);
    }

    public function isBeforeAccountingCutoff(StripeTransaction $transaction): bool
    {
        $occurredAt = $transaction->stripe_created_at ?? $transaction->created_at;

        if ($occurredAt === null) {
            return false;
        }

        return Carbon::parse($occurredAt)->lt(Carbon::parse(self::ACCOUNTING_CUTOFF_AT));
    }

    public function hasOperationalWallesterAccount(StripeTransaction $transaction): bool
    {
        $wallesterAccount = $transaction->customer?->wallesterAccountFor($transaction->currency);

        return $wallesterAccount !== null && $wallesterAccount->status->isOperational();
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

    public function dailyImmediateGrossUsedCents(StripeTransaction $transaction): int
    {
        return (int) StripeTransaction::query()
            ->where('customer_id', $transaction->customer_id)
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->where('created_at', '>=', now()->startOfDay())
            ->when($transaction->exists, fn ($query) => $query->where('id', '!=', $transaction->id))
            ->where('immediate_gross_amount', '>', 0)
            ->sum('immediate_gross_amount');
    }
}

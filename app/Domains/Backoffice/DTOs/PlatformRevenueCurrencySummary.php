<?php

namespace App\Domains\Backoffice\DTOs;

readonly class PlatformRevenueCurrencySummary
{
    /**
     * @param  list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int, fx_cents: int}>  $monthlyBreakdown
     */
    public function __construct(
        public string $currency,
        public int $accruedCents,
        public int $settledCents,
        public int $pendingCents,
        public int $monthAccruedCents,
        public int $monthSettledCents,
        public int $monthPendingCents,
        public int $fxCents,
        public int $monthFxCents,
        public int $transactionCount,
        public int $fxTransactionCount,
        public int $settledTransactionCount,
        public int $pendingTransactionCount,
        public array $monthlyBreakdown,
    ) {}

    public function billedCents(): int
    {
        return $this->accruedCents + $this->fxCents;
    }

    public function monthBilledCents(): int
    {
        return $this->monthAccruedCents + $this->monthFxCents;
    }

    public function billedTransactionCount(): int
    {
        return $this->transactionCount + $this->fxTransactionCount;
    }
}

<?php

namespace App\Domains\Backoffice\DTOs;

readonly class PlatformRevenueCurrencySummary
{
    /**
     * @param  list<array{month: string, amount_cents: int, settled_cents: int, pending_cents: int}>  $monthlyBreakdown
     */
    public function __construct(
        public string $currency,
        public int $accruedCents,
        public int $settledCents,
        public int $pendingCents,
        public int $monthAccruedCents,
        public int $monthSettledCents,
        public int $monthPendingCents,
        public int $ledgerCents,
        public int $transactionCount,
        public int $settledTransactionCount,
        public int $pendingTransactionCount,
        public bool $ledgerMatchesStripe,
        public array $monthlyBreakdown,
    ) {}
}

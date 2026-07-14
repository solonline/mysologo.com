<?php

namespace App\Domains\Backoffice\DTOs;

readonly class PlatformRevenueCurrencySummary
{
    /**
     * @param  list<array{month: string, amount_cents: int}>  $monthlyBreakdown
     */
    public function __construct(
        public string $currency,
        public int $accruedCents,
        public int $monthAccruedCents,
        public int $ledgerCents,
        public int $transactionCount,
        public bool $ledgerMatchesStripe,
        public array $monthlyBreakdown,
    ) {}
}

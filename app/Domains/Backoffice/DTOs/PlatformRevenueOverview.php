<?php

namespace App\Domains\Backoffice\DTOs;

readonly class PlatformRevenueOverview
{
    /**
     * @param  list<PlatformRevenueCurrencySummary>  $currencies
     */
    public function __construct(
        public array $currencies,
        public bool $ledgerMatchesStripe,
    ) {}

    public function primary(): ?PlatformRevenueCurrencySummary
    {
        if ($this->currencies === []) {
            return null;
        }

        foreach ($this->currencies as $summary) {
            if ($summary->currency === 'EUR') {
                return $summary;
            }
        }

        return $this->currencies[0];
    }
}

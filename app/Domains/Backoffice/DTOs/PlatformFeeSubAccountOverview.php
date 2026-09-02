<?php

namespace App\Domains\Backoffice\DTOs;

readonly class PlatformFeeSubAccountOverview
{
    public function __construct(
        public string $currency,
        public int $onSubCents,
        public int $awaitingLiquidityCents,
        public int $awaitingStripeCents,
    ) {}

    public static function empty(string $currency = 'EUR'): self
    {
        return new self($currency, 0, 0, 0);
    }

    public function hasBreakdown(): bool
    {
        return $this->onSubCents > 0
            || $this->awaitingLiquidityCents > 0
            || $this->awaitingStripeCents > 0;
    }
}

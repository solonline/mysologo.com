<?php

namespace App\Domains\Wallet\DTOs;

readonly class TopupSettlementAllocation
{
    public function __construct(
        public int $immediateGrossCents,
        public int $wallesterFundingNetCents,
        public int $stripeDeferredNetCents,
    ) {}

    public function requiresWallesterFunding(): bool
    {
        return $this->wallesterFundingNetCents > 0;
    }

    public function requiresStripeDeferredSettlement(): bool
    {
        return $this->stripeDeferredNetCents > 0;
    }
}

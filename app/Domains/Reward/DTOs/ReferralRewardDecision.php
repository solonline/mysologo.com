<?php

namespace App\Domains\Reward\DTOs;

use App\Domains\Reward\Enums\RewardStatus;
use Carbon\CarbonInterface;

readonly class ReferralRewardDecision
{
    public function __construct(
        public RewardStatus $status,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?CarbonInterface $qualifiedAt = null,
    ) {}
}

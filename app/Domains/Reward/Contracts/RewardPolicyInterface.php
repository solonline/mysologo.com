<?php

namespace App\Domains\Reward\Contracts;

use App\Domains\Reward\DTOs\ReferralRewardDecision;
use App\Models\Customer;

/**
 * Decide se uma indicação gera recompensa. Hoje: cliente activo = eligible, valor ainda indefinido.
 */
interface RewardPolicyInterface
{
    public function evaluate(Customer $referred): ReferralRewardDecision;
}

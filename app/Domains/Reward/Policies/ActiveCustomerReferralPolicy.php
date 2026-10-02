<?php

namespace App\Domains\Reward\Policies;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Reward\Contracts\RewardPolicyInterface;
use App\Domains\Reward\DTOs\ReferralRewardDecision;
use App\Domains\Reward\Enums\RewardStatus;
use App\Models\Customer;
use Illuminate\Support\Carbon;

class ActiveCustomerReferralPolicy implements RewardPolicyInterface
{
    public function evaluate(Customer $referred): ReferralRewardDecision
    {
        $status = $referred->status instanceof CustomerLifecycleStatus
            ? $referred->status
            : CustomerLifecycleStatus::tryFrom((string) $referred->status);

        if ($status !== CustomerLifecycleStatus::ACTIVE) {
            return new ReferralRewardDecision(status: RewardStatus::Pending);
        }

        return new ReferralRewardDecision(
            status: RewardStatus::Eligible,
            amountCents: null,
            currency: null,
            qualifiedAt: Carbon::now(),
        );
    }
}

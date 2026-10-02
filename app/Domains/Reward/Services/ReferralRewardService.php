<?php

namespace App\Domains\Reward\Services;

use App\Domains\Reward\Contracts\RewardPolicyInterface;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardProgram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class ReferralRewardService
{
    public function __construct(
        protected RewardPolicyInterface $policy,
    ) {}

    public function recordFromApprovedCustomer(Customer $referred): ?Reward
    {
        $referred = $referred->fresh() ?? $referred;
        $referrerId = $referred->referred_by_customer_id;

        if ($referrerId === null || $referrerId === $referred->id) {
            return null;
        }

        $existing = Reward::query()->where('referred_id', $referred->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $decision = $this->policy->evaluate($referred);

        try {
            $reward = Reward::query()->create([
                'customer_id' => $referrerId,
                'program_id' => RewardProgram::referral()->id,
                'referred_id' => $referred->id,
                'status' => $decision->status,
                'qualified_at' => $decision->qualifiedAt,
                'amount_cents' => $decision->amountCents,
                'currency' => $decision->currency,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Reward::query()->where('referred_id', $referred->id)->first();
        }

        Log::channel('reward')->info('referral_reward_recorded', [
            'customer_id' => $reward->customer_id,
            'program_id' => $reward->program_id,
            'referred_id' => $reward->referred_id,
            'status' => $reward->status->value,
        ]);

        return $reward;
    }

    public function listForCustomer(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return Reward::query()
            ->where('customer_id', $customer->id)
            ->with(['program', 'referred'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Materializa rewards de indicação para customers.referred_by_customer_id já existentes.
     *
     * @return array{created: int, existing: int, ignored: int}
     */
    public function backfillFromExistingReferrals(): array
    {
        $created = 0;
        $existing = 0;
        $ignored = 0;

        Customer::query()
            ->whereNotNull('referred_by_customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($customers) use (&$created, &$existing, &$ignored): void {
                foreach ($customers as $referred) {
                    $had = Reward::query()->where('referred_id', $referred->id)->exists();
                    $reward = $this->recordFromApprovedCustomer($referred);

                    if ($reward === null) {
                        $ignored++;

                        continue;
                    }

                    if ($had) {
                        $existing++;

                        continue;
                    }

                    $created++;
                }
            });

        return compact('created', 'existing', 'ignored');
    }
}

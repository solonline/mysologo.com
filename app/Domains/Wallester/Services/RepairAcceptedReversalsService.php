<?php

declare(strict_types=1);

namespace App\Domains\Wallester\Services;

use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Collection;
use Throwable;

class RepairAcceptedReversalsService
{
    public function __construct(
        protected FundingPartnerMovementService $movements,
        protected WallesterPlatformFxFeeService $platformFxFees,
    ) {}

    /**
     * @return Collection<int, FundingPartnerMovement>
     */
    public function execute(?int $movementId = null): Collection
    {
        $query = FundingPartnerMovement::query()
            ->where('type', FundingPartnerMovementType::CardRefund)
            ->where('status', FundingPartnerMovementStatus::Failed)
            ->where('failure_reason', 'AcceptedReversal');

        if ($movementId !== null) {
            $query->whereKey($movementId);
        }

        $repaired = collect();

        $query->each(function (FundingPartnerMovement $movement) use ($repaired): void {
            $account = $movement->fundingAccount;

            if (! $account instanceof CustomerWallesterAccount) {
                return;
            }

            try {
                $completed = $this->movements->completeAcceptedReversal($movement, $account);
                $this->platformFxFees->reverseForReversal($completed, $account);
                $repaired->push($completed->fresh());
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $repaired;
    }
}

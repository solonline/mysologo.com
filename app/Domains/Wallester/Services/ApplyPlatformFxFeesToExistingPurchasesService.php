<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplyPlatformFxFeesToExistingPurchasesService
{
    public function __construct(
        protected WallesterPlatformFxFeeService $platformFxFees,
    ) {}

    /**
     * @return Collection<int, FundingPartnerMovement>
     */
    public function execute(?int $customerId = null): Collection
    {
        $query = FundingPartnerMovement::query()
            ->with(['fundingAccount.customer'])
            ->where('type', FundingPartnerMovementType::CardPurchase)
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->where('fees_amount', '>', 0)
            ->whereNotNull('original_currency')
            ->orderBy('id');

        if ($customerId !== null) {
            $accountIds = CustomerWallesterAccount::query()
                ->where('customer_id', $customerId)
                ->pluck('id');

            $query->where('funding_account_type', CustomerWallesterAccount::class)
                ->whereIn('funding_account_id', $accountIds);
        }

        $processed = collect();

        $query->each(function (FundingPartnerMovement $movement) use ($processed): void {
            $account = $movement->fundingAccount;

            if (! $account instanceof CustomerWallesterAccount) {
                return;
            }

            $originalCurrency = strtoupper((string) $movement->original_currency);
            $accountCurrency = strtoupper((string) $movement->currency);

            if ($originalCurrency === '' || $originalCurrency === $accountCurrency) {
                return;
            }

            try {
                $this->platformFxFees->collect($movement, $account);
                $processed->push($movement->fresh());
            } catch (Throwable $exception) {
                Log::channel('wallester')->warning('wallester_apply_platform_fx_fee_failed', [
                    'movement_id' => $movement->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        });

        return $processed;
    }
}

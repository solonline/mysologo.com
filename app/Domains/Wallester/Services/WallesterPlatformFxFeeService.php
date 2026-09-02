<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Fx\Services\FxSettingsService;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Facades\Log;
use Throwable;

class WallesterPlatformFxFeeService
{
    public function __construct(
        protected FxSettingsService $fxSettings,
        protected FundingPartnerMovementService $movements,
    ) {}

    public function collect(FundingPartnerMovement $movement, CustomerWallesterAccount $account): void
    {
        if (! $this->isCompletedCardPurchase($movement)) {
            return;
        }

        if ((int) $movement->fees_amount <= 0) {
            return;
        }

        $this->splitUnifiedPurchaseAmount($movement);
        $this->collectYoopPercentage($movement, $account);
        $movement->refresh();
        // Linha visível = taxa Wallester + % Yoop. A transferência fx-platform
        // cobra a % na Wallester mas não deve aparecer como segundo débito.
        $this->recordCustomerVisibleFee($movement, $account);
    }

    /**
     * Compras antigas gravavam amount = cotado + taxa Wallester.
     * A row da transação fica só com o valor cotado (`account_amount`).
     */
    private function splitUnifiedPurchaseAmount(FundingPartnerMovement $movement): void
    {
        $quotedCents = $this->quotedAmountCentsFromMetadata($movement);
        $feesCents = (int) $movement->fees_amount;

        if ($quotedCents === null || $quotedCents <= 0 || $feesCents <= 0) {
            return;
        }

        if ((int) $movement->amount !== $quotedCents + $feesCents) {
            return;
        }

        $movement->update(['amount' => $quotedCents]);
        $movement->refresh();
    }

    private function quotedAmountCentsFromMetadata(FundingPartnerMovement $movement): ?int
    {
        $metadata = is_array($movement->metadata) ? $movement->metadata : [];
        $accountAmount = $metadata['account_amount']
            ?? data_get($metadata, 'data.account_amount')
            ?? data_get($metadata, 'settlement_metadata.account_amount');

        if ($accountAmount === null || $accountAmount === '') {
            return null;
        }

        return abs((int) round(((float) $accountAmount) * 100));
    }

    private function collectYoopPercentage(FundingPartnerMovement $movement, CustomerWallesterAccount $account): void
    {
        if ((int) $movement->platform_fees_amount > 0) {
            return;
        }

        if (! $this->isForeignPurchase($movement)) {
            return;
        }

        $percentage = $this->fxSettings->percentage();

        if ($percentage <= 0) {
            return;
        }

        $feeAccountCents = (int) round((int) $movement->amount * $percentage);

        if ($feeAccountCents <= 0) {
            return;
        }

        $customer = $account->customer;

        if (! $customer instanceof Customer) {
            return;
        }

        try {
            $this->movements->transferFromCustomerToPlatformFees(
                from: $customer,
                amountCents: $feeAccountCents,
                currency: strtoupper((string) $movement->currency),
                description: 'Câmbio Yoop',
                idempotencyKey: 'fx-platform:'.$movement->partner_external_id,
                reference: $movement,
            );

            $movement->update(['platform_fees_amount' => $feeAccountCents]);
        } catch (Throwable $exception) {
            Log::channel('wallester')->warning('wallester_platform_fx_fee_failed', [
                'movement_id' => $movement->id,
                'partner_external_id' => $movement->partner_external_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function recordCustomerVisibleFee(FundingPartnerMovement $movement, CustomerWallesterAccount $account): void
    {
        $sum = (int) $movement->fees_amount + (int) $movement->platform_fees_amount;

        if ($sum <= 0) {
            return;
        }

        $this->movements->recordCustomerFxFeeMovement(
            purchase: $movement,
            account: $account,
            amountCents: $sum,
        );
    }

    private function isCompletedCardPurchase(FundingPartnerMovement $movement): bool
    {
        return $movement->type === FundingPartnerMovementType::CardPurchase
            && $movement->status === FundingPartnerMovementStatus::Completed
            && is_string($movement->partner_external_id)
            && $movement->partner_external_id !== '';
    }

    private function isForeignPurchase(FundingPartnerMovement $movement): bool
    {
        $originalCurrency = strtoupper((string) $movement->original_currency);
        $accountCurrency = strtoupper((string) $movement->currency);

        return $originalCurrency !== ''
            && $originalCurrency !== $accountCurrency
            && (int) $movement->amount > 0;
    }
}

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

    public function reverseForReversal(FundingPartnerMovement $refund, CustomerWallesterAccount $account): void
    {
        if ($refund->type !== FundingPartnerMovementType::CardRefund
            || $refund->status !== FundingPartnerMovementStatus::Completed) {
            return;
        }

        $purchase = $this->findOriginalPurchase($refund, $account);

        if (! $purchase instanceof FundingPartnerMovement) {
            return;
        }

        $ratio = $this->reversalRatio($purchase, $refund);

        if ($ratio <= 0) {
            return;
        }

        $feeMovement = FundingPartnerMovement::query()
            ->where('idempotency_key', 'fx-fees:'.$purchase->partner_external_id)
            ->first();

        $wallesterFeeCents = (int) $purchase->fees_amount;
        $yoopFeeCents = (int) $purchase->platform_fees_amount;
        $reversalWallesterCents = (int) round($wallesterFeeCents * $ratio);
        $reversalYoopCents = (int) round($yoopFeeCents * $ratio);

        // A Wallester anula a taxa dela e a % Yoop em lançamentos separados.
        // Arredondar cada parte (e somar) alinha o ledger ao saldo Wallester;
        // arredondar a soma visível pode ficar 1 cêntimo abaixo (ex. 1,40 vs 0,94+0,47).
        $reversalFeeCents = ($wallesterFeeCents > 0 || $yoopFeeCents > 0)
            ? $reversalWallesterCents + $reversalYoopCents
            : (int) round((int) ($feeMovement?->amount ?? 0) * $ratio);

        if ($reversalFeeCents > 0 && $feeMovement instanceof FundingPartnerMovement) {
            $this->movements->creditFxFeeReversal(
                feeMovement: $feeMovement,
                refund: $refund,
                account: $account,
                amountCents: $reversalFeeCents,
            );
        }

        if ($reversalYoopCents <= 0) {
            return;
        }

        $customer = $account->customer;

        if (! $customer instanceof Customer) {
            return;
        }

        try {
            $this->movements->transferFromPlatformFeesToCustomer(
                to: $customer,
                amountCents: $reversalYoopCents,
                currency: strtoupper((string) $purchase->currency),
                description: 'Câmbio Yoop (reversão)',
                idempotencyKey: 'fx-platform-reversal:'.$refund->partner_external_id,
                reference: $refund,
            );
        } catch (Throwable $exception) {
            Log::channel('wallester')->warning('wallester_platform_fx_fee_reversal_failed', [
                'refund_id' => $refund->id,
                'purchase_id' => $purchase->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function findOriginalPurchase(
        FundingPartnerMovement $refund,
        CustomerWallesterAccount $account,
    ): ?FundingPartnerMovement {
        $metadata = is_array($refund->metadata) ? $refund->metadata : [];
        $authorizationId = $metadata['original_authorization_id']
            ?? $metadata['authorization_id']
            ?? data_get($metadata, 'data.original_authorization_id')
            ?? data_get($metadata, 'data.authorization_id');

        if (! is_string($authorizationId) || $authorizationId === '') {
            return null;
        }

        return FundingPartnerMovement::query()
            ->where('funding_account_type', $account::class)
            ->where('funding_account_id', $account->id)
            ->where('type', FundingPartnerMovementType::CardPurchase)
            ->where('partner_external_id', $authorizationId)
            ->first();
    }

    private function reversalRatio(FundingPartnerMovement $purchase, FundingPartnerMovement $refund): float
    {
        $purchaseBase = (int) ($purchase->original_amount ?: $purchase->amount);
        $refundBase = (int) ($refund->original_amount ?: $refund->amount);

        if ($purchaseBase <= 0 || $refundBase <= 0) {
            return 0.0;
        }

        return min(1.0, $refundBase / $purchaseBase);
    }
}

<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\LedgerService;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NormalizeSwallowedWallesterFxPurchasesService
{
    public function __construct(
        protected WallesterCardMovementPayloadMapper $payloadMapper,
        protected LedgerService $ledger,
    ) {}

    /**
     * @return Collection<int, FundingPartnerMovement>
     */
    public function execute(?int $customerId = null): Collection
    {
        $query = FundingPartnerMovement::query()
            ->where('type', FundingPartnerMovementType::CardPurchase)
            ->where(function ($query): void {
                $query->where('metadata->wallester_event_type', 'fee')
                    ->orWhere('metadata->transaction_type', 'like', '%fee%')
                    ->orWhere('metadata->type', 'like', '%Fee%');
            });

        if ($customerId !== null) {
            $accountIds = CustomerWallesterAccount::query()
                ->where('customer_id', $customerId)
                ->pluck('id');

            $query->where('funding_account_type', CustomerWallesterAccount::class)
                ->whereIn('funding_account_id', $accountIds);
        }

        $normalized = collect();

        $query->orderBy('id')->each(function (FundingPartnerMovement $movement) use ($normalized): void {
            $updated = $this->normalize($movement);

            if ($updated instanceof FundingPartnerMovement) {
                $normalized->push($updated);
            }
        });

        return $normalized;
    }

    public function normalize(FundingPartnerMovement $movement): ?FundingPartnerMovement
    {
        $metadata = is_array($movement->metadata) ? $movement->metadata : [];

        if (! $this->payloadMapper->isFxFeeEvent($metadata)) {
            return null;
        }

        $purchase = $metadata['settlement_metadata'] ?? null;

        if (! is_array($purchase) || ! isset($purchase['account_amount'])) {
            return null;
        }

        $purchaseId = (string) ($purchase['id'] ?? '');

        if ($purchaseId === '') {
            return null;
        }

        $amounts = $this->payloadMapper->mapAmounts($purchase);

        if ($amounts['amount_cents'] <= 0) {
            return null;
        }

        $previousAmount = (int) $movement->amount;
        $missingCents = $amounts['amount_cents'] - $previousAmount;

        $feeMetadata = $metadata;
        unset($feeMetadata['settlement_metadata'], $feeMetadata['settlement_attached_at'], $feeMetadata['settlement_partner_external_id']);

        $description = is_string($purchase['merchant_name'] ?? null)
            ? $purchase['merchant_name']
            : $movement->description;

        return DB::transaction(function () use (
            $movement,
            $purchase,
            $purchaseId,
            $amounts,
            $previousAmount,
            $missingCents,
            $feeMetadata,
            $description,
        ): FundingPartnerMovement {
            $movement->update([
                'amount' => $amounts['amount_cents'],
                'currency' => $amounts['currency'],
                'original_amount' => $amounts['original_amount_cents'],
                'original_currency' => $amounts['original_currency'],
                'fees_amount' => $amounts['fees_amount_cents'],
                'partner_external_id' => $purchaseId,
                'idempotency_key' => 'card:'.$purchaseId.':'.$movement->direction?->value,
                'description' => $description,
                'metadata' => array_merge($purchase, [
                    'fx_fee_partner_external_id' => $feeMetadata['id'] ?? $movement->partner_external_id,
                    'fx_fee_metadata' => $feeMetadata,
                ]),
            ]);

            $movement = $movement->fresh();

            if (
                $missingCents > 0
                && $movement->status === FundingPartnerMovementStatus::Completed
                && $movement->fundingAccount instanceof CustomerWallesterAccount
            ) {
                $this->ledger->record(
                    customerId: (int) $movement->fundingAccount->customer_id,
                    currency: (string) $movement->currency,
                    entryType: LedgerEntryType::CARD_PURCHASE,
                    amount: $missingCents,
                    reference: $movement,
                    description: $movement->description,
                    metadata: [
                        'normalized_from_fx_fee' => true,
                        'previous_amount' => $previousAmount,
                    ],
                );
            }

            return $movement;
        });
    }
}

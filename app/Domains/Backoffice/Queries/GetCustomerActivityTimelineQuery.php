<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\CustomerActivityItem;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Support\Collection;

class GetCustomerActivityTimelineQuery
{
    /**
     * @return Collection<int, CustomerActivityItem>
     */
    public function execute(Customer $customer, int $limit = 50): Collection
    {
        $stripeItems = StripeTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', '!=', \App\Domains\Wallet\Enums\TransactionType::MERCHANT_PAYMENT->value)
            ->latest('stripe_created_at')
            ->limit($limit)
            ->get()
            ->map(fn (StripeTransaction $transaction): CustomerActivityItem => $this->fromStripeTransaction($transaction));

        $wallesterAccountIds = CustomerWallesterAccount::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $wallesterItems = collect();

        if ($wallesterAccountIds->isNotEmpty()) {
            $wallesterItems = FundingPartnerMovement::query()
                ->where('funding_account_type', CustomerWallesterAccount::class)
                ->whereIn('funding_account_id', $wallesterAccountIds)
                ->whereIn('type', [
                    FundingPartnerMovementType::CardPurchase,
                    FundingPartnerMovementType::CardRefund,
                    FundingPartnerMovementType::Fee,
                ])
                ->with('wallesterCard')
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (FundingPartnerMovement $movement): CustomerActivityItem => $this->fromWallesterMovement($movement));
        }

        $platformItems = CustomerBalanceAdjustment::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerBalanceAdjustmentStatus::Executed)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerBalanceAdjustment $adjustment): CustomerActivityItem => $this->fromBalanceAdjustment($adjustment));

        return $stripeItems
            ->concat($wallesterItems)
            ->concat($platformItems)
            ->sortByDesc(fn (CustomerActivityItem $item) => $item->occurredAt->getTimestamp())
            ->take($limit)
            ->values();
    }

    private function fromStripeTransaction(StripeTransaction $transaction): CustomerActivityItem
    {
        $direction = $transaction->direction?->value ?? (string) $transaction->direction;

        return new CustomerActivityItem(
            source: 'stripe',
            occurredAt: $transaction->stripe_created_at ?? $transaction->created_at,
            isIncoming: $direction === 'credit',
            amountCents: (int) $transaction->amount,
            netAmountCents: $transaction->net_amount !== null ? (int) $transaction->net_amount : null,
            currency: strtoupper((string) $transaction->currency),
            typeLabel: $transaction->type?->label() ?? ($transaction->payment_method_type ?? 'Stripe'),
            status: (string) ($transaction->status ?? '—'),
            description: $transaction->description,
            bankTransferSenderName: $transaction->bank_transfer_sender_name,
            bankTransferIbanLast4: $transaction->bank_transfer_iban_last4,
            stripeTransaction: $transaction,
            sourceLabel: 'Stripe',
        );
    }

    private function fromWallesterMovement(FundingPartnerMovement $movement): CustomerActivityItem
    {
        $metadata = is_array($movement->metadata) ? $movement->metadata : [];
        $type = $movement->type;
        $direction = $movement->direction;

        $typeLabel = match ($type) {
            FundingPartnerMovementType::CardPurchase => 'Compra cartão',
            FundingPartnerMovementType::CardRefund => 'Reembolso cartão',
            FundingPartnerMovementType::Fee => 'Taxa Wallester',
            default => $type?->value ?? 'Wallester',
        };

        $card = $movement->wallesterCard;
        $cardLabel = $card?->name
            ?? $card?->masked_card_number
            ?? (is_string($metadata['card_id'] ?? null) ? substr($metadata['card_id'], 0, 8).'…' : null);

        return new CustomerActivityItem(
            source: 'wallester',
            occurredAt: $movement->created_at,
            isIncoming: $direction === FundingPartnerMovementDirection::In,
            amountCents: (int) $movement->amount,
            netAmountCents: null,
            currency: strtoupper((string) $movement->currency),
            typeLabel: $typeLabel,
            status: $movement->status?->value ?? (string) $movement->status,
            description: $movement->description,
            wallesterMovement: $movement,
            cardLabel: $cardLabel,
            merchantCity: is_string($metadata['merchant_city'] ?? null) ? $metadata['merchant_city'] : null,
            merchantCategoryCode: is_string($metadata['merchant_category_code'] ?? null) ? $metadata['merchant_category_code'] : null,
            sourceLabel: 'Wallester',
        );
    }

    private function fromBalanceAdjustment(CustomerBalanceAdjustment $adjustment): CustomerActivityItem
    {
        $isCredit = $adjustment->direction === CustomerBalanceAdjustmentDirection::Credit;

        return new CustomerActivityItem(
            source: 'platform',
            occurredAt: $adjustment->updated_at,
            isIncoming: $isCredit,
            amountCents: (int) $adjustment->amount_cents,
            netAmountCents: null,
            currency: strtoupper((string) $adjustment->currency),
            typeLabel: $isCredit ? 'Crédito manual' : 'Débito manual',
            status: 'Executado',
            description: $adjustment->description,
            sourceLabel: 'Banco da Plataforma',
        );
    }
}

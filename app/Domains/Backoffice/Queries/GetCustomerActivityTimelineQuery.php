<?php

namespace App\Domains\Backoffice\Queries;

use App\Domains\Backoffice\DTOs\CustomerActivityItem;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Support\Collection;

class GetCustomerActivityTimelineQuery
{
    public function __construct(
        private readonly FundingPartnerMovementService $movements,
    ) {}

    /**
     * @return Collection<int, CustomerActivityItem>
     */
    public function execute(Customer $customer, int $limit = 50): Collection
    {
        $stripeItems = StripeTransaction::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('type', [
                TransactionType::MERCHANT_PAYMENT,
                TransactionType::P2P,
            ])
            ->with('relatedStripeTransaction.customer')
            ->latest('stripe_created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (StripeTransaction $transaction): CustomerActivityItem => $this->fromStripeTransaction($transaction));

        $wallesterAccounts = CustomerWallesterAccount::query()
            ->where('customer_id', $customer->id)
            ->get();

        foreach ($wallesterAccounts as $account) {
            $this->movements->syncPendingWiseMirrors($account);
        }

        $wallesterAccountIds = $wallesterAccounts->pluck('id');

        $adjustmentMorph = (new CustomerBalanceAdjustment)->getMorphClass();

        $wallesterItems = collect();

        if ($wallesterAccountIds->isNotEmpty()) {
            $wallesterItems = FundingPartnerMovement::query()
                ->customerVisible()
                ->where('funding_account_type', CustomerWallesterAccount::class)
                ->whereIn('funding_account_id', $wallesterAccountIds)
                ->where(function ($query) use ($customer, $adjustmentMorph): void {
                    $query->whereNull('reference_type')
                        ->orWhere('reference_type', '!=', $adjustmentMorph)
                        ->orWhereNotIn(
                            'reference_id',
                            CustomerBalanceAdjustment::query()
                                ->where('customer_id', $customer->id)
                                ->select('id'),
                        );
                })
                ->with(['wallesterCard', 'reference', 'counterpartyWallesterAccount.customer'])
                ->latest('created_at')
                ->latest('id')
                ->limit($limit)
                ->get();

            FundingPartnerMovement::hydrateWiseTransfers($wallesterItems);

            $wallesterItems = $wallesterItems
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
        $counterparty = $transaction->p2pCounterparty();

        return new CustomerActivityItem(
            source: 'stripe',
            occurredAt: $transaction->stripe_created_at ?? $transaction->created_at,
            isIncoming: $direction === 'credit',
            amountCents: (int) $transaction->amount,
            netAmountCents: $transaction->resolvedNetAmount(),
            feeCents: $transaction->resolvedFeeTotal(),
            availableOn: $transaction->available_on,
            fundsAvailable: $transaction->customerFundsAvailable(),
            currency: strtoupper((string) $transaction->currency),
            typeLabel: $transaction->type?->label() ?? ($transaction->payment_method_type ?? 'Stripe'),
            status: $transaction->customerFacingStatus(),
            description: $transaction->description,
            bankTransferSenderName: $transaction->bank_transfer_sender_name,
            bankTransferIbanLast4: $transaction->bank_transfer_iban_last4,
            p2pCounterpartyName: $counterparty?->name,
            p2pCounterpartyUsername: $counterparty?->username,
            p2pCounterpartyRole: $transaction->p2pCounterpartyRole(),
            stripeTransaction: $transaction,
            sourceLabel: 'Stripe',
        );
    }

    private function fromWallesterMovement(FundingPartnerMovement $movement): CustomerActivityItem
    {
        $metadata = is_array($movement->metadata) ? $movement->metadata : [];
        $direction = $movement->direction;

        $typeLabel = $movement->displayTypeLabel();

        $card = $movement->wallesterCard;
        $cardLabel = $card?->name
            ?? $card?->masked_card_number
            ?? (is_string($metadata['card_id'] ?? null) ? substr($metadata['card_id'], 0, 8).'…' : null);

        $isP2p = $movement->isP2pTransfer();
        $counterparty = $isP2p ? $movement->counterpartyWallesterAccount?->customer : null;

        return new CustomerActivityItem(
            source: 'wallester',
            occurredAt: $movement->created_at,
            isIncoming: $direction === FundingPartnerMovementDirection::In,
            amountCents: (int) $movement->amount,
            netAmountCents: null,
            currency: strtoupper((string) $movement->currency),
            typeLabel: $typeLabel,
            status: $movement->customerFacingStatus() ?? (string) $movement->status,
            description: $movement->displayDescription(),
            p2pCounterpartyName: $counterparty?->name,
            p2pCounterpartyUsername: $counterparty?->username,
            p2pCounterpartyRole: $isP2p
                ? ($direction === FundingPartnerMovementDirection::In ? 'sender' : 'recipient')
                : null,
            wallesterMovement: $movement,
            cardLabel: $cardLabel,
            merchantCity: is_string($metadata['merchant_city'] ?? null) ? $metadata['merchant_city'] : null,
            merchantCategoryCode: is_string($metadata['merchant_category_code'] ?? null) ? $metadata['merchant_category_code'] : null,
            sourceLabel: $movement->displaySourceLabel(),
            fxDetailLabel: $movement->fxDetailLabel(),
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
            typeLabel: $isCredit ? 'Crédito Yoop' : 'Débito Yoop',
            status: 'Executado',
            description: $adjustment->description,
            sourceLabel: $isCredit ? 'Crédito Yoop' : 'Débito Yoop',
        );
    }
}

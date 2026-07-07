<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;

class WallesterCardMovementIngestService
{
    public function __construct(
        protected IntegrationAccountService $integrationAccounts,
        protected FundingPartnerMovementService $movements,
        protected WallesterFundingTransferDriver $wallesterDriver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): void
    {
        $eventType = strtolower((string) ($payload['event_type'] ?? $payload['type'] ?? ''));

        if (! $this->isCardSpendEvent($eventType, $payload)) {
            return;
        }

        $accountId = (string) ($payload['account_id'] ?? $payload['account']['id'] ?? '');
        $externalId = (string) ($payload['id'] ?? $payload['transaction_id'] ?? $payload['authorization_id'] ?? '');

        if ($accountId === '' || $externalId === '') {
            return;
        }

        $account = $this->integrationAccounts->findWallesterByExternalId($accountId);

        if (! $account instanceof CustomerWallesterAccount) {
            return;
        }

        $amountCents = $this->wallesterDriver->amountToCents(
            $payload['account_amount'] ?? $payload['amount'] ?? $payload['authorization_amount'] ?? 0,
        );

        if ($amountCents <= 0) {
            return;
        }

        $isRefund = str_contains($eventType, 'refund') || str_contains($eventType, 'reversal');
        $movementType = $isRefund
            ? FundingPartnerMovementType::CardRefund
            : FundingPartnerMovementType::CardPurchase;
        $direction = $isRefund
            ? FundingPartnerMovementDirection::In
            : FundingPartnerMovementDirection::Out;

        $card = $this->resolveCard($payload, $account);

        $this->movements->recordCardMovement(
            account: $account,
            type: $movementType,
            direction: $direction,
            amountCents: $amountCents,
            currency: (string) ($payload['account_currency_code'] ?? $payload['currency_code'] ?? 'EUR'),
            partnerExternalId: $externalId,
            source: FundingPartnerMovementSource::Webhook,
            card: $card,
            description: is_string($payload['merchant_name'] ?? null) ? $payload['merchant_name'] : null,
            metadata: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCardSpendEvent(string $eventType, array $payload): bool
    {
        if (in_array($eventType, [
            'authorization',
            'transaction',
            'card_transaction',
            'card_authorization',
            'purchase',
            'refund',
            'reversal',
        ], true)) {
            return true;
        }

        return isset($payload['card_id']) || isset($payload['authorization_amount']) || isset($payload['merchant_name']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCard(array $payload, CustomerWallesterAccount $account): ?CustomerWallesterCard
    {
        $cardId = $payload['card_id'] ?? null;

        if (! is_string($cardId) || $cardId === '') {
            return null;
        }

        return CustomerWallesterCard::query()
            ->where('customer_wallester_account_id', $account->id)
            ->where('wallester_card_id', $cardId)
            ->first();
    }
}

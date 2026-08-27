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
        protected WallesterCardMovementPayloadMapper $payloadMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(
        array $payload,
        FundingPartnerMovementSource $source = FundingPartnerMovementSource::Webhook,
    ): void {
        $payload = $this->normalizePayload($payload);

        if ($this->payloadMapper->isFxFeeEvent($payload)) {
            return;
        }

        if (! $this->isCardSpendEvent($payload) || $this->isDeclined($payload)) {
            return;
        }

        $externalId = (string) ($payload['id'] ?? $payload['transaction_id'] ?? $payload['authorization_id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $account = $this->resolveAccount($payload);

        if (! $account instanceof CustomerWallesterAccount) {
            return;
        }

        $amounts = $this->payloadMapper->mapAmounts($payload);

        if ($amounts['amount_cents'] <= 0) {
            return;
        }

        $isRefund = $this->isRefundEvent($payload);
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
            amountCents: $amounts['amount_cents'],
            currency: $amounts['currency'],
            partnerExternalId: $externalId,
            source: $source,
            card: $card,
            description: is_string($payload['merchant_name'] ?? null) ? $payload['merchant_name'] : null,
            metadata: $payload,
            originalAmountCents: $amounts['original_amount_cents'],
            originalCurrency: $amounts['original_currency'],
            feesAmountCents: $amounts['fees_amount_cents'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data) || $data === []) {
            return $payload;
        }

        $envelopeType = strtolower((string) ($payload['event_type'] ?? $payload['type'] ?? ''));
        $envelopeEvent = strtolower((string) ($payload['event'] ?? ''));

        $normalized = array_merge($payload, $data);
        $normalized['wallester_event_type'] = $envelopeType;
        $normalized['wallester_event'] = $envelopeEvent;
        $normalized['transaction_type'] = strtolower((string) ($data['type'] ?? ''));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCardSpendEvent(array $payload): bool
    {
        $envelopeType = strtolower((string) (
            $payload['wallester_event_type']
            ?? $payload['event_type']
            ?? $payload['type']
            ?? ''
        ));
        $transactionType = strtolower((string) ($payload['transaction_type'] ?? $payload['type'] ?? ''));
        $group = strtolower((string) ($payload['group'] ?? ''));

        if (in_array($envelopeType, [
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

        if (in_array($transactionType, [
            'paywavelowvaluepurchase',
            'purchase',
            'card_payment',
            'refund',
            'card_refund',
            'reversal',
        ], true) || str_contains($transactionType, 'purchase')) {
            return true;
        }

        if (in_array($group, ['purchase', 'refund', 'reversal'], true)) {
            return true;
        }

        return isset($payload['card_id'])
            || isset($payload['authorization_amount'])
            || isset($payload['merchant_name']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isDeclined(array $payload): bool
    {
        if (($payload['is_declined'] ?? false) === true) {
            return true;
        }

        $responseCode = strtolower((string) ($payload['response_code'] ?? ''));

        if ($responseCode !== '' && $responseCode !== 'approved') {
            return true;
        }

        $status = strtolower((string) ($payload['status'] ?? ''));

        return in_array($status, ['declined', 'failed', 'rejected'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isRefundEvent(array $payload): bool
    {
        if (($payload['is_reversal'] ?? false) === true) {
            return true;
        }

        $envelopeType = strtolower((string) ($payload['wallester_event_type'] ?? $payload['event_type'] ?? $payload['type'] ?? ''));
        $transactionType = strtolower((string) ($payload['transaction_type'] ?? $payload['type'] ?? ''));
        $group = strtolower((string) ($payload['group'] ?? ''));

        return str_contains($envelopeType, 'refund')
            || str_contains($envelopeType, 'reversal')
            || str_contains($transactionType, 'refund')
            || str_contains($transactionType, 'reversal')
            || in_array($group, ['refund', 'reversal'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAccount(array $payload): ?CustomerWallesterAccount
    {
        $accountId = (string) ($payload['account_id'] ?? $payload['account']['id'] ?? '');

        if ($accountId !== '') {
            return $this->integrationAccounts->findWallesterByExternalId($accountId);
        }

        $card = $this->resolveCard($payload, null);

        return $card?->wallesterAccount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCard(array $payload, ?CustomerWallesterAccount $account): ?CustomerWallesterCard
    {
        $cardId = $payload['card_id'] ?? null;

        if (! is_string($cardId) || $cardId === '') {
            return null;
        }

        $query = CustomerWallesterCard::query()->where('wallester_card_id', $cardId);

        if ($account instanceof CustomerWallesterAccount) {
            $query->where('customer_wallester_account_id', $account->id);
        }

        return $query->first();
    }
}

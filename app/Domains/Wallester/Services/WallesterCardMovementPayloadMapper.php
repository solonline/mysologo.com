<?php

namespace App\Domains\Wallester\Services;

class WallesterCardMovementPayloadMapper
{
    public function __construct(
        protected WallesterFundingTransferDriver $wallesterDriver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isFxFeeEvent(array $payload): bool
    {
        $envelopeType = strtolower((string) (
            $payload['wallester_event_type']
            ?? $payload['event_type']
            ?? ''
        ));

        if ($envelopeType === 'fee') {
            return true;
        }

        $transactionType = strtolower((string) ($payload['transaction_type'] ?? $payload['type'] ?? ''));

        if ($transactionType === '') {
            return false;
        }

        if (str_contains($transactionType, 'foreignexchange') || str_contains($transactionType, 'foreign_exchange')) {
            return true;
        }

        return str_contains($transactionType, 'fee');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     amount_cents: int,
     *     currency: string,
     *     fees_amount_cents: int|null,
     *     original_amount_cents: int|null,
     *     original_currency: string|null
     * }
     */
    public function mapAmounts(array $payload): array
    {
        $accountAmount = $payload['account_amount']
            ?? $payload['amount']
            ?? $payload['authorization_amount']
            ?? $payload['transaction_amount']
            ?? 0;

        $feesCents = $this->optionalCents($payload['fees_amount'] ?? null);
        $originalCurrency = $payload['transaction_currency_code'] ?? null;

        return [
            'amount_cents' => $this->wallesterDriver->absoluteAmountToCents($accountAmount) + ($feesCents ?? 0),
            'currency' => strtoupper((string) ($payload['account_currency_code'] ?? $payload['currency_code'] ?? 'EUR')),
            'fees_amount_cents' => $feesCents,
            'original_amount_cents' => $this->optionalCents($payload['transaction_amount'] ?? null),
            'original_currency' => is_string($originalCurrency) && $originalCurrency !== ''
                ? strtoupper($originalCurrency)
                : null,
        ];
    }

    private function optionalCents(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $cents = $this->wallesterDriver->absoluteAmountToCents($amount);

        return $cents > 0 ? $cents : null;
    }
}

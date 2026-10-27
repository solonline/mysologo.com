<?php

namespace App\Domains\Wise\Gateways;

use App\Domains\Wise\Clients\WiseHttpClient;
use App\Domains\Wise\Contracts\WiseGatewayInterface;

class WiseGateway implements WiseGatewayInterface
{
    public function __construct(
        protected WiseHttpClient $client,
    ) {}

    public function listBalances(): array
    {
        return array_map(
            fn (array $balance): array => $this->normalizeBalance($balance),
            $this->client->listBalances(),
        );
    }

    public function createRecipient(array $payload): array
    {
        return $this->client->createRecipient($payload);
    }

    public function createQuote(array $payload): array
    {
        return $this->client->createQuote($payload);
    }

    public function createTransfer(array $payload): array
    {
        return $this->client->createTransfer($payload);
    }

    public function fundTransfer(string $transferId, string $balanceId): array
    {
        return $this->client->fundTransfer($transferId, $balanceId);
    }

    public function createBatchGroup(string $sourceCurrency, string $name): array
    {
        return $this->client->createBatchGroup($sourceCurrency, $name);
    }

    public function createBatchGroupTransfer(string $batchGroupId, array $payload): array
    {
        return $this->client->createBatchGroupTransfer($batchGroupId, $payload);
    }

    public function getBatchGroup(string $batchGroupId): array
    {
        return $this->client->getBatchGroup($batchGroupId);
    }

    public function completeBatchGroup(string $batchGroupId, int $version): array
    {
        return $this->client->completeBatchGroup($batchGroupId, $version);
    }

    public function downloadTransferReceipt(string $transferId): string
    {
        return $this->client->downloadTransferReceipt($transferId);
    }

    /**
     * @param  array<string, mixed>  $balance
     * @return array{id: int|string|null, currency: string|null, amount: float|null, reserved_amount: float|null, type: string|null}
     */
    private function normalizeBalance(array $balance): array
    {
        $id = $balance['id'] ?? null;

        return [
            'id' => is_int($id) || is_string($id) ? $id : null,
            'currency' => isset($balance['currency']) ? strtoupper((string) $balance['currency']) : null,
            'amount' => $this->moneyValue($balance['amount'] ?? null),
            'reserved_amount' => $this->moneyValue($balance['reservedAmount'] ?? $balance['reserved_amount'] ?? null),
            'type' => isset($balance['type']) ? (string) $balance['type'] : null,
        ];
    }

    private function moneyValue(mixed $amount): ?float
    {
        if (is_array($amount) && isset($amount['value']) && is_numeric($amount['value'])) {
            return (float) $amount['value'];
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        return null;
    }
}

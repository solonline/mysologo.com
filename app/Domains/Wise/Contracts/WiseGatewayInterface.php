<?php

namespace App\Domains\Wise\Contracts;

interface WiseGatewayInterface
{
    /**
     * @return list<array{id: int|string|null, currency: string|null, amount: float|null, reserved_amount: float|null, type: string|null}>
     */
    public function listBalances(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRecipient(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createQuote(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransfer(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function fundTransfer(string $transferId, string $balanceId): array;

    /**
     * @return array<string, mixed>
     */
    public function createBatchGroup(string $sourceCurrency, string $name): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBatchGroupTransfer(string $batchGroupId, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getBatchGroup(string $batchGroupId): array;

    /**
     * @return array<string, mixed>
     */
    public function completeBatchGroup(string $batchGroupId, int $version): array;

    public function downloadTransferReceipt(string $transferId): string;
}

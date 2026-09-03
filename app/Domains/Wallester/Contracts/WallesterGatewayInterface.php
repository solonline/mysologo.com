<?php

namespace App\Domains\Wallester\Contracts;

interface WallesterGatewayInterface
{
    public function forCurrency(string $currency): self;

    public function authenticate(): string;

    public function createAccount(array $data): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, accounts: array<int, array<string, mixed>>}
     */
    public function listAccounts(array $query, ?string $auditUserId = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getAccount(string $accountId, ?string $auditUserId = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getCard(string $cardId, ?string $auditUserId = null): array;

    public function blockCard(string $cardId, ?string $auditUserId = null): array;

    public function unblockCard(string $cardId, ?string $auditUserId = null): array;

    public function closeCard(string $cardId, ?string $auditUserId = null): array;

    public function getEncryptedCardNumber(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): string;

    public function getEncryptedCvv2(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): string;

    public function createCard(array $data, ?string $auditUserId = null): array;

    public function activateCard(string $cardId, ?string $auditUserId = null): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>}
     */
    public function listAccountTransactions(string $accountId, array $query, ?string $auditUserId = null): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>}
     */
    public function listCardTransactions(string $cardId, array $query, ?string $auditUserId = null): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId, ?string $auditUserId = null, array $query = []): array;

    /**
     * @param  array{from_account_id: string, to_account_id: string, amount: float, description?: string}  $data
     * @return array<string, mixed>
     */
    public function accountTransfer(array $data, ?string $auditUserId = null): array;
}

<?php

namespace App\Domains\Wallester\Gateways;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WallesterGateway implements WallesterGatewayInterface
{
    public function __construct(
        protected WallesterHttpClient $client,
    ) {}

    public function authenticate(): string
    {
        return $this->client->authenticate();
    }

    public function createAccount(array $data): array
    {
        $response = $this->run('createAccount', fn () => $this->client->createAccount($data), [
            'request' => $data,
        ]);

        return $this->normalizeAccountResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, accounts: array<int, array<string, mixed>>}
     */
    public function listAccounts(array $query, ?string $auditUserId = null): array
    {
        $response = $this->run(
            'listAccounts',
            fn () => $this->client->listAccounts($query, $auditUserId),
            [
                'query' => $query,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeAccountsListResponse(is_array($response) ? $response : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccount(string $accountId, ?string $auditUserId = null): array
    {
        $response = $this->run(
            'getAccount',
            fn () => $this->client->getAccount($accountId, $auditUserId),
            [
                'account_id' => $accountId,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeAccountResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{total_records_number: int, accounts: array<int, array<string, mixed>>}
     */
    private function normalizeAccountsListResponse(array $response): array
    {
        return [
            'total_records_number' => (int) ($response['total_records_number'] ?? 0),
            'accounts' => is_array($response['accounts'] ?? null) ? $response['accounts'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeAccountResponse(array $response): array
    {
        if (isset($response['account']) && is_array($response['account'])) {
            return $response['account'];
        }

        return $response;
    }

    public function getCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->run('getCard', fn () => $this->client->getCard($cardId, $auditUserId), [
            'card_id' => $cardId,
            'audit_user_id' => $auditUserId,
        ]);

        return $this->normalizeCardResponse(is_array($response) ? $response : []);
    }

    public function getEncryptedCardNumber(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): string
    {
        $response = $this->run(
            'getEncryptedCardNumber',
            fn () => $this->client->getEncryptedCardNumber($cardId, $publicKeyBase64, $auditUserId),
            [
                'card_id' => $cardId,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeEncryptedSensitiveValue(is_array($response) ? $response : [], 'encrypted_card_number');
    }

    public function getEncryptedCvv2(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): string
    {
        $response = $this->run(
            'getEncryptedCvv2',
            fn () => $this->client->getEncryptedCvv2($cardId, $publicKeyBase64, $auditUserId),
            [
                'card_id' => $cardId,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeEncryptedSensitiveValue(is_array($response) ? $response : [], 'encrypted_cvv2');
    }

    public function createCard(array $data, ?string $auditUserId = null): array
    {
        $response = $this->run('createCard', fn () => $this->client->createCard($data, $auditUserId), [
            'request' => $data,
            'audit_user_id' => $auditUserId,
        ]);

        return $this->normalizeCardResponse(is_array($response) ? $response : []);
    }

    public function activateCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->run('activateCard', fn () => $this->client->activateCard($cardId, $auditUserId), [
            'card_id' => $cardId,
            'audit_user_id' => $auditUserId,
        ]);

        return $this->normalizeCardResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeCardResponse(array $response): array
    {
        if (isset($response['card']) && is_array($response['card'])) {
            return $response['card'];
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function normalizeEncryptedSensitiveValue(array $response, string $field): string
    {
        $value = $response[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Wallester response is missing [{$field}].");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>}
     */
    public function listAccountTransactions(string $accountId, array $query, ?string $auditUserId = null): array
    {
        $response = $this->run(
            'listAccountTransactions',
            fn () => $this->client->listAccountTransactions($accountId, $query, $auditUserId),
            [
                'account_id' => $accountId,
                'query' => $query,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeTransactionsListResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>}
     */
    public function listCardTransactions(string $cardId, array $query, ?string $auditUserId = null): array
    {
        $response = $this->run(
            'listCardTransactions',
            fn () => $this->client->listCardTransactions($cardId, $query, $auditUserId),
            [
                'card_id' => $cardId,
                'query' => $query,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeTransactionsListResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId, ?string $auditUserId = null, array $query = []): array
    {
        $response = $this->run(
            'getTransaction',
            fn () => $this->client->getTransaction($transactionId, $auditUserId, $query),
            [
                'transaction_id' => $transactionId,
                'query' => $query,
                'audit_user_id' => $auditUserId,
            ],
        );

        return $this->normalizeTransactionResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>}
     */
    private function normalizeTransactionsListResponse(array $response): array
    {
        return [
            'total_records_number' => (int) ($response['total_records_number'] ?? 0),
            'transactions' => is_array($response['transactions'] ?? null) ? $response['transactions'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeTransactionResponse(array $response): array
    {
        if (isset($response['transaction']) && is_array($response['transaction'])) {
            return $response['transaction'];
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function run(string $operation, callable $fn, array $context = []): mixed
    {
        try {
            $result = $fn();

            Log::channel('wallester')->info("wallester_api: {$operation}", array_filter([
                ...$context,
                'response' => $result,
            ]));

            return $result;
        } catch (RequestException $e) {
            $this->logRequestException($operation, $e, $context);

            throw $e;
        } catch (\Throwable $e) {
            Log::channel('wallester')->error("wallester_unexpected: {$operation}", array_filter([
                ...$context,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logRequestException(string $operation, RequestException $e, array $context = []): void
    {
        $response = $e->getResponse();

        Log::channel('wallester')->error("wallester_error: {$operation}", array_filter([
            ...$context,
            'http_status' => $response?->getStatusCode(),
            'response_body' => $response !== null ? (string) $response->getBody() : null,
            'message' => $e->getMessage(),
        ]));
    }
}

<?php

namespace App\Domains\Wallester\Clients;

use App\Domains\Wallester\Services\WallesterSettingsService;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;

class WallesterHttpClient
{
    protected Client $httpClient;

    protected string $currency = 'eur';

    protected ?string $apiKey = null;

    protected ?string $privateKey = null;

    protected ?string $productCode = null;

    public function __construct(
        protected WallesterSettingsService $settings,
    ) {
        $this->httpClient = new Client([
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
    }

    public function forCurrency(string $currency): self
    {
        $normalizedCurrency = strtolower($currency);

        if ($normalizedCurrency !== $this->currency || $this->apiKey === null) {
            $this->currency = $normalizedCurrency;
            $this->apiKey = $this->settings->apiKey($normalizedCurrency);
            $this->privateKey = $this->settings->privateKey($normalizedCurrency);
            $this->productCode = $this->settings->productCode($normalizedCurrency);
            $this->httpClient = new Client([
                'base_uri' => $this->settings->baseUrl(),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        }

        return $this;
    }

    public function authenticate(): string
    {
        $this->ensureCurrencyResolved();

        $claims = [
            'api_key' => $this->apiKey,
            'ts' => time(),
        ];

        return JWT::encode($claims, (string) $this->privateKey, 'RS256');
    }

    /** @return array<string, string> */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->authenticate(),
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, string> */
    protected function getRequestHeaders(?string $auditUserId = null): array
    {
        $this->ensureCurrencyResolved();

        $headers = $this->getAuthHeaders();

        if (is_string($this->productCode) && $this->productCode !== '') {
            $headers['X-Product-Code'] = $this->productCode;
        }

        $headers['X-Audit-Source-Type'] = $this->settings->auditSourceType();

        if ($auditUserId !== null && $auditUserId !== '') {
            $headers['X-Audit-User-Id'] = $auditUserId;
        }

        return $headers;
    }

    public function createAccount(array $data, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post('accounts', [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => $data,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function listAccounts(array $query, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get('accounts', [
            'headers' => $this->getRequestHeaders($auditUserId),
            'query' => $query,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function getAccount(string $accountId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("accounts/{$accountId}", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function getEncryptedCardNumber(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post("cards/{$cardId}/encrypted-card-number", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => [
                'public_key' => $publicKeyBase64,
            ],
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function getEncryptedCvv2(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post("cards/{$cardId}/encrypted-cvv2", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => [
                'public_key' => $publicKeyBase64,
            ],
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function getCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("cards/{$cardId}", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function createCard(array $data, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post('cards', [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => $data,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    public function activateCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->patch("cards/{$cardId}/activate", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function listAccountTransactions(string $accountId, array $query, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("accounts/{$accountId}/transactions", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'query' => $query,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function listCardTransactions(string $cardId, array $query, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("cards/{$cardId}/transactions", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'query' => $query,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function getTransaction(string $transactionId, ?string $auditUserId = null, array $query = []): array
    {
        $response = $this->httpClient->get("transactions/{$transactionId}", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'query' => $query,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    /**
     * @param  array{from_account_id: string, to_account_id: string, amount: float, description?: string}  $data
     */
    public function accountTransfer(array $data, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post('payments/account-transfer', [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => $data,
        ]);

        return $this->decodeJson($response->getBody()->getContents());
    }

    private function ensureCurrencyResolved(): void
    {
        if ($this->apiKey === null) {
            $this->forCurrency('eur');
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        
        return is_array($decoded) ? $decoded : [];
    }
}

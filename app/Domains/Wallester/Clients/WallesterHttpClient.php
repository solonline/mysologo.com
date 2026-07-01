<?php

namespace App\Domains\Wallester\Clients;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;

class WallesterHttpClient
{
    protected Client $httpClient;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $privateKey;

    public function __construct()
    {
        $configuredBaseUrl = config('services.wallester.base_url', 'https://api-frontend.wallester.com/v1/');
        $this->baseUrl = rtrim((string) $configuredBaseUrl, '/').'/';
        $this->apiKey = config('services.wallester.api_key');
        $this->privateKey = config('services.wallester.private_key');
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function authenticate(): string
    {
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
        $headers = $this->getAuthHeaders();

        $productCode = config('services.wallester.product_code');

        if (is_string($productCode) && $productCode !== '') {
            $headers['X-Product-Code'] = $productCode;
        }

        $headers['X-Audit-Source-Type'] = (string) config('services.wallester.audit_source_type', 'Backend');

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

        return json_decode($response->getBody()->getContents(), true);
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

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getAccount(string $accountId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("accounts/{$accountId}", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getEncryptedCardNumber(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post("cards/{$cardId}/encrypted-card-number", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => [
                'public_key' => $publicKeyBase64,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getEncryptedCvv2(string $cardId, string $publicKeyBase64, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post("cards/{$cardId}/encrypted-cvv2", [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => [
                'public_key' => $publicKeyBase64,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->get("cards/{$cardId}", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createCard(array $data, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->post('cards', [
            'headers' => $this->getRequestHeaders($auditUserId),
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function activateCard(string $cardId, ?string $auditUserId = null): array
    {
        $response = $this->httpClient->patch("cards/{$cardId}/activate", [
            'headers' => $this->getRequestHeaders($auditUserId),
        ]);

        return json_decode($response->getBody()->getContents(), true);
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

        return json_decode($response->getBody()->getContents(), true);
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

        return json_decode($response->getBody()->getContents(), true);
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

        return json_decode($response->getBody()->getContents(), true);
    }
}

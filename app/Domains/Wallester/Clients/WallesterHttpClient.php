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
        $this->baseUrl = config('services.wallester.base_url', 'https://api-frontend.wallester.com/v1/');
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
            'Authorization' => 'Bearer ' . $this->authenticate(),
            'Accept' => 'application/json',
        ];
    }

    public function createAccount(array $data): array
    {
        $response = $this->httpClient->post('accounts', [
            'headers' => $this->getAuthHeaders(),
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getCard(string $cardId): array
    {
        $response = $this->httpClient->get("cards/{$cardId}", [
            'headers' => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createCard(array $data): array
    {
        $response = $this->httpClient->post('cards', [
            'headers' => $this->getAuthHeaders(),
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}

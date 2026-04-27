<?php

namespace App\Clients;


use GuzzleHttp\Client;
use App\Contracts\WallesterProviderInterface;
use Firebase\JWT\JWT;

class WallesterClient implements WallesterProviderInterface
{
    protected $httpClient;
    protected $baseUrl;
    protected $apiKey;
    protected $privateKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api-frontend.wallester.com/v1/';

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
        $now = time();
        $claims = [
            'api_key' => $this->apiKey,
            'ts' => $now,
        ];
        $jwt = JWT::encode($claims, $this->privateKey, 'RS256');
        return $jwt;
    }

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

    // Adicione outros métodos conforme necessário
}

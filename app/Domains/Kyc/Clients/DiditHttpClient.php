<?php

namespace App\Domains\Kyc\Clients;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DiditHttpClient
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.didit.base_url');
        $this->apiKey = (string) config('services.didit.api_key');
        $this->apiSecret = (string) config('services.didit.api_secret');
    }

    public function createSession(array $data): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-api-key' => $this->apiSecret,
        ])->post($this->baseUrl . '/session', $data);
    }

    /** @return array<string, mixed> */
    public function getSessionStatus(string $sessionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/session/' . $sessionId . '/decision');

        return $response->json() ?? [];
    }
}

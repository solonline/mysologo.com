<?php

namespace App\Domains\Kyc\Clients;

use App\Domains\Settings\Services\DiditSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DiditHttpClient
{
    public function __construct(
        protected DiditSettingsService $diditSettings,
    ) {}

    public function createSession(array $data): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->diditSettings->apiKey(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-api-key' => $this->diditSettings->apiSecret(),
        ])->post($this->diditSettings->baseUrl().'/session', $data);
    }

    /** @return array<string, mixed> */
    public function getSessionStatus(string $sessionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->diditSettings->apiKey(),
            'Accept' => 'application/json',
        ])->get($this->diditSettings->baseUrl().'/session/'.$sessionId.'/decision');

        return $response->json() ?? [];
    }
}

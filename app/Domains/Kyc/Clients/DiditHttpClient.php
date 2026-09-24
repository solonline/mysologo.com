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
        return Http::withHeaders($this->headers([
            'Content-Type' => 'application/json',
        ]))->post($this->diditSettings->baseUrl().'/session', $data);
    }

    /** @return array<string, mixed> */
    public function getSessionStatus(string $sessionId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->diditSettings->baseUrl().'/session/'.$sessionId.'/decision');

        return $response->json() ?? [];
    }

    /** @param array<string, string> $extra */
    private function headers(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer '.$this->diditSettings->apiKey(),
            'Accept' => 'application/json',
            'x-api-key' => $this->diditSettings->apiSecret(),
        ], $extra);
    }
}

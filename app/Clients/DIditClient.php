<?php
namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class DIditClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;

    protected string $workflowId;

    public function __construct()
    {
        $this->baseUrl = config('services.didit.base_url');
        $this->apiKey = config('services.didit.api_key');
        $this->apiSecret = config('services.didit.api_secret');
        $this->workflowId = config('services.didit.workflow_id');
    }

    public function createSession(array $data): Response
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-api-key' => $this->apiSecret,
        ])->post($this->baseUrl . '/session', $data);
        return $response;
    }

    public function getSessionStatus(string $sessionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/session/' . $sessionId . '/decision');

        return $response->json();
    }
}

<?php

namespace App\Clients;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

class App360Client
{
    protected string $baseUrl;
    protected string $authToken;

    public function __construct(protected SettingsService $settingsService)
    {
        $this->baseUrl = $this->settingsService->getByKey('app360_base_url');
        $this->authToken = $this->settingsService->getByKey('app360_auth_token');
    }

    /**
     * Envia SMS via 360NRS
     * @param array $to Números de destino
     * @param string $from Remetente
     * @param string $message Mensagem
     * @return array
     */
    public function sendSms(array $to, string $from, string $message): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $this->authToken,
        ])->post($this->baseUrl . '/sms', [
            'to' => $to,
            'from' => $from,
            'message' => $message,
        ]);

        return [
            'status' => $response->successful() ? 'success' : 'failed',
            'body' => $response->json(),
            'http_code' => $response->status(),
        ];
    }
}

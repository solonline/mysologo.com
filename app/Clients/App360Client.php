<?php

namespace App\Clients;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

class App360Client
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct(protected SettingsService $settingsService)
    {
        $this->baseUrl = $this->settingsService->getByKey('app360_base_url');
        $this->username = $this->settingsService->getByKey('app360_username');
        $this->password = $this->settingsService->getByKey('app360_password');
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
        $basicAuth = base64_encode($this->username . ':' . $this->password);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $basicAuth,
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

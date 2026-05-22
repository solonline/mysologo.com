<?php

namespace App\Clients;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

class SmsClient
{
    protected string $baseUrl;
    protected string $key;
    protected string $secret;
    protected string $authToken;

    public function __construct(protected SettingsService $settingsService)
    {
        $this->baseUrl = $this->settingsService->getByKey('sms.base_url');
        $this->key = $this->settingsService->getByKey('sms.key');
        $this->secret = $this->settingsService->getByKey('sms.secret');
        $this->authToken = $this->settingsService->getByKey('sms.auth_token');
    }

    public function sendSms(array $to, string $from, string $message): array
    {
        $results = [];

        foreach ($to as $number) {
            $msisdn = (int) ltrim($number, '+');

            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->authToken,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/mobile/single', [
                'recipient' => $msisdn,
                'sender'    => $from,
                'message'   => $message,
            ]);

            $results[] = [
                'status'    => $response->successful() ? 'success' : 'failed',
                'body'      => $response->json(),
                'http_code' => $response->status(),
            ];
        }

        return count($results) === 1 ? $results[0] : $results;
    }
}

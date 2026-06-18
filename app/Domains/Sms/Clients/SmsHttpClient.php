<?php

namespace App\Domains\Sms\Clients;

use App\Domains\Settings\Services\SettingsService;
use Illuminate\Support\Facades\Http;

class SmsHttpClient
{
    protected string $baseUrl;

    protected string $key;

    protected string $secret;

    protected string $authToken;

    public function __construct(protected SettingsService $settingsService)
    {
        $this->baseUrl = (string) $this->settingsService->getByKey('sms.base_url');
        $this->key = (string) $this->settingsService->getByKey('sms.key');
        $this->secret = (string) $this->settingsService->getByKey('sms.secret');
        $this->authToken = (string) $this->settingsService->getByKey('sms.auth_token');
    }

  /** @return array<string, mixed>|list<array<string, mixed>> */
    public function sendSms(array $to, string $from, string $message): array
    {
        $results = [];

        foreach ($to as $number) {
            $msisdn = (int) ltrim($number, '+');

            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->authToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/mobile/single', [
                'recipient' => $msisdn,
                'sender' => $from,
                'message' => $message,
            ]);

            $results[] = [
                'status' => $response->successful() ? 'success' : 'failed',
                'body' => $response->json(),
                'http_code' => $response->status(),
            ];
        }

        return count($results) === 1 ? $results[0] : $results;
    }
}

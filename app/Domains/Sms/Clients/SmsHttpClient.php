<?php

namespace App\Domains\Sms\Clients;

use App\Domains\Settings\Services\SettingsService;
use App\Domains\Sms\Support\SmsLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

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
        $endpoint = rtrim($this->baseUrl, '/').'/mobile/single';

        SmsLogger::info('sms_send_request', [
            'endpoint' => $endpoint,
            'sender' => $from,
            'recipients' => $to,
            'recipient_count' => count($to),
            'message' => $message,
            'base_url_configured' => $this->baseUrl !== '',
            'auth_token' => $this->authToken,
            'key' => $this->key,
            'secret' => $this->secret,
        ]);

        if ($this->baseUrl === '' || $this->authToken === '') {
            SmsLogger::error('sms_send_skipped_missing_config', [
                'base_url_configured' => $this->baseUrl !== '',
                'auth_token' => $this->authToken,
            ]);

            return [
                'status' => 'failed',
                'body' => ['error' => 'SMS provider is not configured.'],
                'http_code' => 0,
            ];
        }

        foreach ($to as $number) {
            $msisdn = (int) ltrim($number, '+');

            try {
                $response = Http::timeout(15)->withHeaders([
                    'Authorization' => 'Token '.$this->authToken,
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'recipient' => $msisdn,
                    'sender' => $from,
                    'message' => $message,
                ]);

                $result = [
                    'status' => $response->successful() ? 'success' : 'failed',
                    'body' => $response->json(),
                    'http_code' => $response->status(),
                ];

                $logContext = [
                    'recipient' => $number,
                    'msisdn' => $msisdn,
                    'sender' => $from,
                    'http_code' => $result['http_code'],
                    'provider_status' => $result['status'],
                    'provider_body' => $result['body'],
                    'result' => $result,
                ];

                if ($response->successful()) {
                    SmsLogger::info('sms_send_success', $logContext);
                } else {
                    SmsLogger::error('sms_send_failed', $logContext);
                }

                $results[] = $result;
            } catch (Throwable $exception) {
                SmsLogger::error('sms_send_exception', [
                    'recipient' => $number,
                    'msisdn' => $msisdn,
                    'sender' => $from,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $results[] = [
                    'status' => 'failed',
                    'body' => ['error' => $exception->getMessage()],
                    'http_code' => 0,
                ];
            }
        }

        return count($results) === 1 ? $results[0] : $results;
    }
}

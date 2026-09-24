<?php

namespace App\Domains\Push\Clients;

use App\Domains\Push\Support\FcmSendResult;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function send(string $token, string $title, ?string $body, array $data): FcmSendResult
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            Log::warning('FCM credentials are not configured.');

            return new FcmSendResult(sent: false);
        }

        $accessToken = $this->accessToken($credentials);
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->post($this->sendUrl($credentials['project_id']), [
                'message' => [
                    'token' => $token,
                    'notification' => array_filter([
                        'title' => $title,
                        'body' => $body,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'data' => $data,
                ],
            ]);

        if ($response->successful()) {
            return new FcmSendResult(sent: true);
        }

        if ($this->isInvalidToken($response->json() ?? [])) {
            return new FcmSendResult(sent: false, invalidToken: true);
        }

        throw new RuntimeException('FCM send failed with HTTP '.$response->status());
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (! is_string($decoded[$key] ?? null) || $decoded[$key] === '') {
                return null;
            }
        }

        return [
            'project_id' => $decoded['project_id'],
            'client_email' => $decoded['client_email'],
            'private_key' => $decoded['private_key'],
        ];
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () use ($credentials): string {
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'sub' => $credentials['client_email'],
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
                'scope' => self::SCOPE,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('FCM access token request failed.');
            }

            return $response->json('access_token');
        });
    }

    private function sendUrl(string $projectId): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isInvalidToken(array $body): bool
    {
        $status = $body['error']['status'] ?? null;

        if ($status === 'NOT_FOUND') {
            return true;
        }

        foreach ($body['error']['details'] ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if (in_array($detail['errorCode'] ?? null, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return true;
            }
        }

        return false;
    }
}

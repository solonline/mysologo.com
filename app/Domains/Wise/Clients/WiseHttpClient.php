<?php

namespace App\Domains\Wise\Clients;

use App\Domains\Wise\Services\WiseSettingsService;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WiseHttpClient
{
    public const API_VERSION = '2026Q4';

    public function __construct(
        protected WiseSettingsService $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listBalances(): array
    {
        $response = $this->request()->get(
            $this->endpoint('profiles/'.$this->profileId().'/balances'),
            ['types' => 'STANDARD'],
        );

        $decoded = $this->decode($response, 'list_balances');

        if (! array_is_list($decoded)) {
            throw new ApiException(
                'wise_list_balances_failed',
                502,
                'Wise returned an invalid balance list.',
                'wise',
            );
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRecipient(array $payload): array
    {
        $response = $this->request()->post($this->endpoint('accounts'), $payload);

        return $this->decode($response, 'create_recipient');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createQuote(array $payload): array
    {
        $response = $this->request()->post(
            $this->endpoint('profiles/'.$this->profileId().'/quotes'),
            $payload,
        );

        return $this->decode($response, 'create_quote');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransfer(array $payload): array
    {
        $response = $this->request()->post($this->endpoint('transfers'), $payload);

        return $this->decode($response, 'create_transfer');
    }

    /**
     * @return array<string, mixed>
     */
    public function createBatchGroup(string $sourceCurrency, string $name): array
    {
        $response = $this->request()->post(
            $this->endpoint('profiles/'.$this->profileId().'/batch-groups'),
            [
                'sourceCurrency' => strtoupper($sourceCurrency),
                'name' => $name,
            ],
        );

        return $this->decode($response, 'create_batch_group');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBatchGroupTransfer(string $batchGroupId, array $payload): array
    {
        $response = $this->request()->post(
            $this->endpoint('profiles/'.$this->profileId().'/batch-groups/'.rawurlencode($batchGroupId).'/transfers'),
            $payload,
        );

        return $this->decode($response, 'create_batch_transfer');
    }

    /**
     * @return array<string, mixed>
     */
    public function getBatchGroup(string $batchGroupId): array
    {
        $response = $this->request()->get(
            $this->endpoint('profiles/'.$this->profileId().'/batch-groups/'.rawurlencode($batchGroupId)),
        );

        return $this->decode($response, 'get_batch_group');
    }

    /**
     * @return array<string, mixed>
     */
    public function completeBatchGroup(string $batchGroupId, int $version): array
    {
        $response = $this->request()->patch(
            $this->endpoint('profiles/'.$this->profileId().'/batch-groups/'.rawurlencode($batchGroupId)),
            [
                'status' => 'COMPLETED',
                'version' => $version,
            ],
        );

        return $this->decode($response, 'complete_batch_group');
    }

    public function downloadTransferReceipt(string $transferId): string
    {
        $response = $this->request()
            ->accept('application/pdf')
            ->get($this->endpoint('transfers/'.rawurlencode($transferId).'/receipt.pdf'));

        if (! $response->successful() || $response->body() === '') {
            Log::channel('wise')->warning('wise_api_error', [
                'action' => 'download_transfer_receipt',
                'status' => $response->status(),
            ]);

            throw new ApiException(
                'wise_download_transfer_receipt_failed',
                $this->mapStatus($response->status()),
                'Wise transfer receipt could not be downloaded.',
                'wise',
            );
        }

        return $response->body();
    }

    /**
     * @return array<string, mixed>
     */
    public function fundTransfer(string $transferId, string $balanceId): array
    {
        $url = $this->endpoint('profiles/'.$this->profileId().'/transfers/'.rawurlencode($transferId).'/payments');
        $payload = [
            'type' => 'BALANCE',
            'balanceId' => (int) $balanceId,
        ];

        $response = $this->request()->post($url, $payload);
        $approval = $response->header('x-2fa-approval');

        if ($response->status() === 403 && is_string($approval) && $approval !== '') {
            $response = $this->request()
                ->contentType('application/json')
                ->withHeaders([
                    'X-idempotence-uuid' => (string) Str::uuid(),
                    'x-2fa-approval' => $approval,
                    'X-Signature' => $this->signApproval($approval),
                    'X-Signature-Proxy' => 'true',
                ])
                ->post($url, $payload);
        }

        return $this->decode($response, 'fund_transfer');
    }

    private function signApproval(string $approval): string
    {
        $privateKey = openssl_pkey_get_private($this->settings->scaPrivateKey());

        if ($privateKey === false) {
            throw new ApiException(
                'wise_sca_key_invalid',
                503,
                'Wise SCA private key could not be read.',
                'wise',
            );
        }

        $signed = openssl_sign($approval, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new ApiException(
                'wise_sca_sign_failed',
                503,
                'Wise payment approval could not be signed.',
                'wise',
            );
        }

        return str_replace(["\r", "\n", ' '], '', base64_encode($signature));
    }

    private function request(): PendingRequest
    {
        if (! $this->settings->hasCredentials()) {
            throw new ApiException(
                'wise_not_configured',
                503,
                'Wise credentials are not configured.',
                'wise',
            );
        }

        return Http::timeout(20)
            ->acceptJson()
            ->withToken($this->settings->apiToken())
            ->withHeaders([
                'X-External-Correlation-Id' => (string) Str::uuid(),
            ]);
    }

    private function profileId(): string
    {
        return rawurlencode($this->settings->profileId());
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->settings->baseUrl(), '/').'/'.self::API_VERSION.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function decode(Response $response, string $action): array
    {
        if (! $response->successful()) {
            $body = $response->json();

            Log::channel('wise')->warning('wise_api_error', [
                'action' => $action,
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new ApiException(
                'wise_'.$action.'_failed',
                $this->mapStatus($response->status()),
                $this->messageFromBody($body),
                'wise',
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new ApiException(
                'wise_'.$action.'_failed',
                502,
                'Wise returned an invalid response.',
                'wise',
            );
        }

        return $json;
    }

    private function mapStatus(int $status): int
    {
        return in_array($status, [400, 404, 409, 422], true) ? $status : 502;
    }

    private function messageFromBody(mixed $body): string
    {
        if (! is_array($body)) {
            return 'Wise request failed.';
        }

        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        if (isset($body['error']) && is_string($body['error']) && $body['error'] !== '') {
            return $body['error'];
        }

        $errors = $body['errors'] ?? null;

        if (is_array($errors) && isset($errors[0])) {
            $first = $errors[0];

            if (is_string($first) && $first !== '') {
                return $first;
            }

            if (is_array($first)) {
                $text = $first['message'] ?? $first['error'] ?? null;

                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        return 'Wise request failed.';
    }
}

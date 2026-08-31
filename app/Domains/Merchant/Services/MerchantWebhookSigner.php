<?php

namespace App\Domains\Merchant\Services;

use App\Models\Merchant;

class MerchantWebhookSigner
{
    public const HEADER_TIMESTAMP = 'X-Yoop-Timestamp';

    public const HEADER_SIGNATURE = 'X-Yoop-Signature';

    public const HEADER_DELIVERY_ID = 'X-Yoop-Delivery-Id';

    public function generateRawSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(32));
    }

    /**
     * @return array{timestamp: int, signature: string, signed_payload: string}
     */
    public function sign(string $rawSecret, string $rawBody, ?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;
        $signedPayload = $timestamp.'.'.$rawBody;
        $signature = hash_hmac('sha256', $signedPayload, $rawSecret);

        return [
            'timestamp' => $timestamp,
            'signature' => 'v1='.$signature,
            'signed_payload' => $signedPayload,
        ];
    }

    public function resolveRawSecret(Merchant $merchant): ?string
    {
        $secret = $merchant->webhook_secret;

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        return $secret;
    }
}

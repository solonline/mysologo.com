<?php

namespace App\Domains\Wise\Services;

class WiseWebhookSignature
{
    public function __construct(
        private readonly WiseSettingsService $settings,
    ) {}

    public function verify(string $payload, string $signatureHeader): bool
    {
        $signature = base64_decode($signatureHeader, true);

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        foreach ($this->settings->webhookVerificationKeys() as $pem) {
            $key = openssl_pkey_get_public($pem);

            if ($key !== false && openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }
}

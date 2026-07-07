<?php

namespace App\Domains\Wallester\Services;

use RuntimeException;

class WallesterSensitiveDataDecryptor
{
    public function __construct(
        private readonly WallesterCardRsaKeyService $rsaKeys,
    ) {}

    public function decryptCardNumber(string $encryptedPayload, string $currency = 'eur'): string
    {
        return $this->decrypt($encryptedPayload, 'CardNumber', $currency);
    }

    public function decryptCvv2(string $encryptedPayload, string $currency = 'eur'): string
    {
        return $this->decrypt($encryptedPayload, 'CVV2', $currency);
    }

    private function decrypt(string $encryptedPayload, string $messageLabel, string $currency): string
    {
        $ciphertext = $this->extractCiphertext($encryptedPayload, $messageLabel);

        try {
            return WallesterRsaOaep::decrypt(
                $ciphertext,
                $this->rsaKeys->privateKeyPem($currency),
                $messageLabel,
            );
        } catch (RuntimeException $exception) {
            throw new RuntimeException("Unable to decrypt Wallester {$messageLabel} payload.", previous: $exception);
        }
    }

    private function extractCiphertext(string $encryptedPayload, string $messageLabel): string
    {
        if (is_array($encryptedPayload)) {
            $encryptedPayload = $encryptedPayload['encrypted_card_number']
                ?? $encryptedPayload['encrypted_cvv2']
                ?? $encryptedPayload['payload']
                ?? $encryptedPayload['value']
                ?? '';
        }

        if (! is_string($encryptedPayload) || trim($encryptedPayload) === '') {
            throw new RuntimeException("Invalid Wallester {$messageLabel} payload.");
        }

        $normalized = str_replace(["\r\n", "\n", "\r"], '', trim($encryptedPayload));
        $normalized = str_replace("-----BEGIN {$messageLabel} MESSAGE-----", '', $normalized);
        $normalized = str_replace("-----END {$messageLabel} MESSAGE-----", '', $normalized);

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw new RuntimeException("Invalid Wallester {$messageLabel} payload encoding.");
        }

        return $decoded;
    }
}

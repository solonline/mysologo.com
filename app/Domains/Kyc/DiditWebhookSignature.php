<?php

namespace App\Domains\Kyc;

final class DiditWebhookSignature
{
    public const MAX_AGE_SECONDS = 300;

    /**
     * @param  array{signature_v2?: ?string, signature?: ?string, signature_simple?: ?string, timestamp?: ?string}  $headers
     */
    public static function verify(string $rawBody, string $secret, array $headers, ?int $now = null): bool
    {
        $timestamp = $headers['timestamp'] ?? null;

        if (! is_string($timestamp) || $timestamp === '' || $secret === '') {
            return false;
        }

        $now ??= time();

        if (abs($now - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $signatureV2 = $headers['signature_v2'] ?? null;

        if (is_string($signatureV2) && $signatureV2 !== '' && self::matchesV2($rawBody, $signatureV2, $secret)) {
            return true;
        }

        $signatureRaw = $headers['signature'] ?? null;

        if (is_string($signatureRaw) && $signatureRaw !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureRaw)) {
            return true;
        }

        $signatureSimple = $headers['signature_simple'] ?? null;

        if (is_string($signatureSimple) && $signatureSimple !== '' && self::matchesSimple($rawBody, $signatureSimple, $secret)) {
            return true;
        }

        return false;
    }

    public static function canonicalizeJson(string $rawBody): ?string
    {
        $decoded = json_decode($rawBody, false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $canonical = json_encode(
            self::canonicalize($decoded),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($canonical) ? $canonical : null;
    }

    private static function matchesV2(string $rawBody, string $signature, string $secret): bool
    {
        $canonical = self::canonicalizeJson($rawBody);

        if ($canonical === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $canonical, $secret), $signature);
    }

    private static function matchesSimple(string $rawBody, string $signature, string $secret): bool
    {
        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return false;
        }

        $canonical = implode(':', [
            (string) ($decoded['timestamp'] ?? ''),
            (string) ($decoded['session_id'] ?? ''),
            (string) ($decoded['status'] ?? ''),
            (string) ($decoded['webhook_type'] ?? ''),
        ]);

        return hash_equals(hash_hmac('sha256', $canonical, $secret), $signature);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $props = get_object_vars($value);
            ksort($props, SORT_STRING);
            $sorted = new \stdClass;

            foreach ($props as $key => $item) {
                $sorted->{$key} = self::canonicalize($item);
            }

            return $sorted;
        }

        if (is_array($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        return $value;
    }
}

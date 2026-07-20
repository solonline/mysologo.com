<?php

namespace App\Domains\Sms\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SmsLogger
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $event, array $context = []): void
    {
        $sanitized = self::sanitize($context);

        try {
            Log::channel('sms')->{$level}($event, $sanitized);
        } catch (Throwable $exception) {
            Log::channel(config('logging.default', 'stack'))->warning('sms_logger_fallback', [
                'event' => $event,
                'original_level' => $level,
                'logging_exception' => $exception->getMessage(),
                'context' => $sanitized,
            ]);
        }
    }

    /** @param  array<string, mixed>  $context */
    private static function sanitize(array $context): array
    {
        if (isset($context['message']) && is_string($context['message'])) {
            $context['message'] = self::redactVerificationCodes($context['message']);
        }

        if (isset($context['code']) && is_string($context['code'])) {
            $context['code'] = self::mask($context['code']);
        }

        foreach (['auth_token', 'secret', 'key'] as $secretKey) {
            if (array_key_exists($secretKey, $context)) {
                $context[$secretKey] = self::maskSecret($context[$secretKey]);
            }
        }

        return $context;
    }

    private static function redactVerificationCodes(string $message): string
    {
        return (string) preg_replace('/\b\d{6}\b/', '******', $message);
    }

    private static function mask(string $value): string
    {
        $length = strlen($value);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2).str_repeat('*', max(0, $length - 4)).substr($value, -2);
    }

    private static function maskSecret(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '[empty]';
        }

        return '[set:'.strlen($value).' chars]';
    }
}

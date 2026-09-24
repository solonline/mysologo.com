<?php

namespace App\Domains\Whatsapp\Gateways;

use App\Domains\Whatsapp\Contracts\WhatsappClientInterface;
use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Domains\Whatsapp\Enums\WhatsappMediaType;
use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use App\Domains\Whatsapp\Support\WhatsappLogger;
use Throwable;

class WhatsappGateway implements WhatsappGatewayInterface
{
    public function __construct(
        protected WhatsappClientInterface $client,
        protected WhatsappSettingsService $settings,
    ) {}

    public function sendText(string $to, string $text): WhatsappSendResult
    {
        $number = $this->normalizeNumber($to);

        if ($number === '' || trim($text) === '') {
            return WhatsappSendResult::failed('Número ou texto inválido.', 422);
        }

        if (! $this->settings->hasCredentials()) {
            WhatsappLogger::error('whatsapp_send_skipped_missing_config', [
                'to' => $number,
            ]);

            return WhatsappSendResult::failed('WhatsApp provider is not configured.');
        }

        try {
            $response = $this->client->sendText($number, $text);
        } catch (WhatsappException $exception) {
            WhatsappLogger::error('whatsapp_send_text_exception', [
                'to' => $number,
                'message' => $exception->getMessage(),
            ]);

            return WhatsappSendResult::failed($exception->getMessage());
        } catch (Throwable $exception) {
            WhatsappLogger::error('whatsapp_send_text_unexpected', [
                'to' => $number,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return WhatsappSendResult::failed('Erro de comunicação com o serviço de WhatsApp.');
        }

        $result = WhatsappSendResult::fromProvider(
            (int) ($response['http_code'] ?? 0),
            is_array($response['body'] ?? null) ? $response['body'] : [],
        );

        $this->logSendResult('text', $number, $result);

        return $result;
    }

    public function sendMedia(
        string $to,
        string $media,
        WhatsappMediaType $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): WhatsappSendResult {
        $number = $this->normalizeNumber($to);

        if ($number === '' || trim($media) === '') {
            return WhatsappSendResult::failed('Número ou media inválidos.', 422);
        }

        if (! $this->settings->hasCredentials()) {
            WhatsappLogger::error('whatsapp_send_media_skipped_missing_config', [
                'to' => $number,
            ]);

            return WhatsappSendResult::failed('WhatsApp provider is not configured.');
        }

        try {
            $response = $this->client->sendMedia(
                $number,
                $media,
                $mediaType->value,
                $caption,
                $fileName,
                $mimeType,
            );
        } catch (WhatsappException $exception) {
            WhatsappLogger::error('whatsapp_send_media_exception', [
                'to' => $number,
                'media_type' => $mediaType->value,
                'message' => $exception->getMessage(),
            ]);

            return WhatsappSendResult::failed($exception->getMessage());
        } catch (Throwable $exception) {
            WhatsappLogger::error('whatsapp_send_media_unexpected', [
                'to' => $number,
                'media_type' => $mediaType->value,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return WhatsappSendResult::failed('Erro de comunicação com o serviço de WhatsApp.');
        }

        $result = WhatsappSendResult::fromProvider(
            (int) ($response['http_code'] ?? 0),
            is_array($response['body'] ?? null) ? $response['body'] : [],
        );

        $this->logSendResult('media', $number, $result, ['media_type' => $mediaType->value]);

        return $result;
    }

    public function connectionState(): array
    {
        if (! $this->settings->hasCredentials()) {
            return [
                'connected' => false,
                'state' => null,
                'body' => ['error' => 'WhatsApp provider is not configured.'],
                'http_code' => 0,
            ];
        }

        try {
            $response = $this->client->connectionState();
        } catch (Throwable $exception) {
            WhatsappLogger::error('whatsapp_connection_state_exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'connected' => false,
                'state' => null,
                'body' => ['error' => $exception->getMessage()],
                'http_code' => 0,
            ];
        }

        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $state = $this->extractState($body);

        return [
            'connected' => $state === 'open',
            'state' => $state,
            'body' => $body,
            'http_code' => (int) ($response['http_code'] ?? 0),
        ];
    }

    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', trim($number)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /** @param  array<string, mixed>  $extra */
    private function logSendResult(string $kind, string $to, WhatsappSendResult $result, array $extra = []): void
    {
        $context = array_merge($extra, [
            'to' => $to,
            'http_code' => $result->httpCode,
            'message_id' => $result->messageId,
            'provider_status' => $result->status,
            'error' => $result->error,
        ]);

        if ($result->success) {
            WhatsappLogger::info("whatsapp_send_{$kind}_success", $context);

            return;
        }

        WhatsappLogger::error("whatsapp_send_{$kind}_failed", $context);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractState(array $body): ?string
    {
        $instance = $body['instance'] ?? null;

        if (is_array($instance) && isset($instance['state']) && is_string($instance['state'])) {
            return strtolower($instance['state']);
        }

        if (isset($body['state']) && is_string($body['state'])) {
            return strtolower($body['state']);
        }

        return null;
    }
}

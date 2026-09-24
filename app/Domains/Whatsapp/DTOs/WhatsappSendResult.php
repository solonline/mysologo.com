<?php

namespace App\Domains\Whatsapp\DTOs;

readonly class WhatsappSendResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public bool $success,
        public int $httpCode,
        public ?string $messageId,
        public ?string $status,
        public array $body,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromProvider(int $httpCode, array $body): self
    {
        $success = $httpCode >= 200 && $httpCode < 300;
        $key = $body['key'] ?? null;
        $messageId = is_array($key) ? ($key['id'] ?? null) : null;

        return new self(
            success: $success,
            httpCode: $httpCode,
            messageId: is_string($messageId) && $messageId !== '' ? $messageId : null,
            status: isset($body['status']) ? (string) $body['status'] : null,
            body: $body,
            error: $success ? null : self::extractError($body),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function failed(string $error, int $httpCode = 0, array $body = []): self
    {
        return new self(
            success: false,
            httpCode: $httpCode,
            messageId: null,
            status: null,
            body: $body,
            error: $error,
        );
    }

    public function isPermanentFailure(): bool
    {
        if ($this->success) {
            return false;
        }

        return in_array($this->httpCode, [400, 401, 403, 404, 422], true)
            || $this->error === 'WhatsApp provider is not configured.';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function extractError(array $body): string
    {
        $response = $body['response'] ?? null;

        if (is_array($response)) {
            $message = $response['message'] ?? null;

            if (is_array($message) && $message !== []) {
                $first = reset($message);

                return is_string($first) ? $first : 'Falha ao enviar mensagem WhatsApp.';
            }

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        foreach (['message', 'error', 'errorMessage'] as $key) {
            if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        return 'Falha ao enviar mensagem WhatsApp.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'http_code' => $this->httpCode,
            'message_id' => $this->messageId,
            'status' => $this->status,
            'body' => $this->body,
            'error' => $this->error,
        ];
    }
}

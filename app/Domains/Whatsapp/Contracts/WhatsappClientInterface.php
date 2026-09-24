<?php

namespace App\Domains\Whatsapp\Contracts;

/**
 * Acesso ao provedor WhatsApp. Cada API tem o seu client.
 */
interface WhatsappClientInterface
{
    /**
     * @return array{http_code: int, body: array<string, mixed>}
     */
    public function sendText(string $number, string $text): array;

    /**
     * @return array{http_code: int, body: array<string, mixed>}
     */
    public function sendMedia(
        string $number,
        string $media,
        string $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): array;

    /**
     * @return array{http_code: int, body: array<string, mixed>}
     */
    public function connectionState(): array;
}

<?php

namespace App\Domains\Whatsapp\Contracts;

use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Domains\Whatsapp\Enums\WhatsappMediaType;

/**
 * Contrato agnóstico de provedor WhatsApp (Evolution API hoje, outro amanhã).
 */
interface WhatsappGatewayInterface
{
    public function sendText(string $to, string $text): WhatsappSendResult;

    public function sendMedia(
        string $to,
        string $media,
        WhatsappMediaType $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): WhatsappSendResult;

    /**
     * @return array{connected: bool, state: ?string, body: array<string, mixed>, http_code: int}
     */
    public function connectionState(): array;
}

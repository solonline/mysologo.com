<?php

namespace App\Domains\Whatsapp\Services;

use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Domains\Whatsapp\Enums\WhatsappMediaType;
use App\Domains\Whatsapp\Jobs\SendWhatsappMediaJob;
use App\Domains\Whatsapp\Jobs\SendWhatsappTextJob;

class WhatsappService
{
    public function __construct(
        protected WhatsappGatewayInterface $gateway,
    ) {}

    public function sendText(string $to, string $text): WhatsappSendResult
    {
        return $this->gateway->sendText($to, $text);
    }

    public function sendTextLater(string $to, string $text): void
    {
        SendWhatsappTextJob::dispatch($to, $text);
    }

    public function sendMedia(
        string $to,
        string $media,
        WhatsappMediaType $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): WhatsappSendResult {
        return $this->gateway->sendMedia($to, $media, $mediaType, $caption, $fileName, $mimeType);
    }

    public function sendMediaLater(
        string $to,
        string $media,
        WhatsappMediaType $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): void {
        SendWhatsappMediaJob::dispatch($to, $media, $mediaType, $caption, $fileName, $mimeType);
    }

    public function isConnected(): bool
    {
        return $this->connectionState()['connected'];
    }

    /**
     * @return array{connected: bool, state: ?string, body: array<string, mixed>, http_code: int}
     */
    public function connectionState(): array
    {
        return $this->gateway->connectionState();
    }
}

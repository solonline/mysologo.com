<?php

namespace App\Domains\Whatsapp\Clients;

use App\Domains\Whatsapp\Contracts\WhatsappClientInterface;
use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use App\Domains\Whatsapp\Support\WhatsappLogger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class EvolutionHttpClient implements WhatsappClientInterface
{
    public const PROVIDER = 'evolution';

    public function __construct(
        protected WhatsappSettingsService $settings,
    ) {}

    public function sendText(string $number, string $text): array
    {
        return $this->post('message/sendText/'.$this->settings->instance(), [
            'number' => $number,
            'text' => $text,
        ]);
    }

    public function sendMedia(
        string $number,
        string $media,
        string $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null,
    ): array {
        $payload = [
            'number' => $number,
            'mediatype' => $mediaType,
            'media' => $media,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        if ($fileName !== null && $fileName !== '') {
            $payload['fileName'] = $fileName;
        }

        if ($mimeType !== null && $mimeType !== '') {
            $payload['mimetype'] = $mimeType;
        }

        return $this->post('message/sendMedia/'.$this->settings->instance(), $payload);
    }

    public function connectionState(): array
    {
        return $this->get('instance/connectionState/'.$this->settings->instance());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{http_code: int, body: array<string, mixed>}
     */
    private function post(string $path, array $payload): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $payload), $path, $payload);
    }

    /**
     * @return array{http_code: int, body: array<string, mixed>}
     */
    private function get(string $path): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get($path), $path);
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     * @param  array<string, mixed>  $payload
     * @return array{http_code: int, body: array<string, mixed>}
     */
    private function send(callable $callback, string $path, array $payload = []): array
    {
        try {
            $response = $callback($this->request());
        } catch (Throwable $exception) {
            WhatsappLogger::error('evolution_http_exception', [
                'path' => $path,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new WhatsappException('Erro de comunicação com a Evolution API.', previous: $exception);
        }

        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : ['raw' => $response->body()];

        WhatsappLogger::info('evolution_http_response', [
            'path' => $path,
            'http_code' => $response->status(),
            'payload_keys' => array_keys($payload),
        ]);

        return [
            'http_code' => $response->status(),
            'body' => $body,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->settings->timeoutSeconds())
            ->acceptJson()
            ->asJson()
            ->baseUrl($this->settings->baseUrl())
            ->withHeaders([
                'apikey' => $this->settings->apiKey(),
            ]);
    }
}

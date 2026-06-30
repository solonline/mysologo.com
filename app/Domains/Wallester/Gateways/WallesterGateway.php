<?php

namespace App\Domains\Wallester\Gateways;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class WallesterGateway implements WallesterGatewayInterface
{
    public function __construct(
        protected WallesterHttpClient $client,
    ) {}

    public function authenticate(): string
    {
        return $this->client->authenticate();
    }

    public function createAccount(array $data): array
    {
        $response = $this->run('createAccount', fn () => $this->client->createAccount($data), [
            'request' => $data,
        ]);

        return $this->normalizeAccountResponse(is_array($response) ? $response : []);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeAccountResponse(array $response): array
    {
        if (isset($response['account']) && is_array($response['account'])) {
            return $response['account'];
        }

        return $response;
    }

    public function getCard(string $cardId): array
    {
        return $this->run('getCard', fn () => $this->client->getCard($cardId), [
            'card_id' => $cardId,
        ]);
    }

    public function createCard(array $data): array
    {
        return $this->run('createCard', fn () => $this->client->createCard($data), [
            'request' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function run(string $operation, callable $fn, array $context = []): mixed
    {
        try {
            $result = $fn();

            Log::channel('wallester')->info("wallester_api: {$operation}", array_filter([
                ...$context,
                'response' => $result,
            ]));

            return $result;
        } catch (RequestException $e) {
            $this->logRequestException($operation, $e, $context);

            throw $e;
        } catch (\Throwable $e) {
            Log::channel('wallester')->error("wallester_unexpected: {$operation}", array_filter([
                ...$context,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logRequestException(string $operation, RequestException $e, array $context = []): void
    {
        $response = $e->getResponse();

        Log::channel('wallester')->error("wallester_error: {$operation}", array_filter([
            ...$context,
            'http_status' => $response?->getStatusCode(),
            'response_body' => $response !== null ? (string) $response->getBody() : null,
            'message' => $e->getMessage(),
        ]));
    }
}

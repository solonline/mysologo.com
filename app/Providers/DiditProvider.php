<?php
namespace App\Providers;

use App\Clients\DIditClient;
use App\Exceptions\ApiException;

class DiditProvider
{
    protected DIditClient $client;

    public function __construct()
    {
        $this->client = new DIditClient();
    }

    public function createSession(array $data)
    {
        $response = $this->client->createSession($data);

        if ($response->status() !== 201) {
            return [
                'success' => false,
                'message' => 'Não foi possível criar a sessão Didit.',
                'detail' => $response->json(),
            ];
        }

        return $response->json();
    }

    public function createSessionResponse($data)
    {
        $response = $this->client->createSession($data);

        if ($response->status() !== 201) {
            return [
                'success' => false,
                'message' => 'Não foi possível criar a sessão Didit.',
                'detail' => $response->json(),
            ];
        }

        return $response->json();
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->client->getSessionStatus($sessionId);
    }
}

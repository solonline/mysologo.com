<?php

namespace App\Providers;

use App\Clients\WallesterClient;
use App\Contracts\WallesterProviderInterface;

class WallesterProvider implements WallesterProviderInterface
{
    protected $client;

    public function __construct(WallesterClient $client)
    {
        $this->client = $client;
    }

    public function authenticate(): string
    {
        return $this->client->authenticate();
    }

    public function createAccount(array $data): array
    {
        return $this->client->createAccount($data);
    }

    public function getCard(string $cardId): array
    {
        return $this->client->getCard($cardId);
    }

    public function createCard(array $data): array
    {
        return $this->client->createCard($data);
    }

    // Adicione outros métodos conforme necessário, delegando para o client
}

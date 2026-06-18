<?php

namespace App\Domains\Wallester\Gateways;

use App\Domains\Wallester\Clients\WallesterHttpClient;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;

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
}

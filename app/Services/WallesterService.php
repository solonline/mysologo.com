<?php

namespace App\Services;

use App\Contracts\WallesterProviderInterface;

class WallesterService
{
    protected $provider;

    public function __construct(WallesterProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function createAccount(array $data): array
    {
        return $this->provider->createAccount($data);
    }

    public function getCard(string $cardId): array
    {
        return $this->provider->getCard($cardId);
    }

    public function createCard(array $data): array
    {
        return $this->provider->createCard($data);
    }

    // Adicione outros métodos conforme necessário
}

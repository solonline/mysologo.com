<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Wallester\Contracts\WallesterGatewayInterface;

class WallesterService
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
    ) {}

    public function createAccount(array $data): array
    {
        return $this->gateway->createAccount($data);
    }

    public function getCard(string $cardId): array
    {
        return $this->gateway->getCard($cardId);
    }

    public function createCard(array $data): array
    {
        return $this->gateway->createCard($data);
    }
}

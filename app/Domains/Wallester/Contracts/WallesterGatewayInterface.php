<?php

namespace App\Domains\Wallester\Contracts;

interface WallesterGatewayInterface
{
    public function authenticate(): string;

    public function createAccount(array $data): array;

    public function getCard(string $cardId): array;

    public function createCard(array $data): array;
}

<?php

namespace App\Contracts;

interface WallesterProviderInterface
{
    public function authenticate(): string;
    public function createAccount(array $data): array;
    public function getCard(string $cardId): array;
    public function createCard(array $data): array;
    // Adicione outros métodos conforme necessário
}

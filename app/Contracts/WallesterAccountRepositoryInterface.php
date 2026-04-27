<?php
namespace App\Contracts;

use App\Models\CustomerWallesterAccount;

interface WallesterAccountRepositoryInterface
{
    public function create(array $data): CustomerWallesterAccount;
    public function findByCustomerId($customerId): ?CustomerWallesterAccount;
}

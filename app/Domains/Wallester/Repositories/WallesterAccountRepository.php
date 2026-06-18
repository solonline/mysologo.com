<?php

namespace App\Domains\Wallester\Repositories;

use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Models\CustomerWallesterAccount;

class WallesterAccountRepository implements WallesterAccountRepositoryInterface
{
    public function create(array $data): CustomerWallesterAccount
    {
        return CustomerWallesterAccount::create($data);
    }

    public function findByCustomerId(int $customerId): ?CustomerWallesterAccount
    {
        return CustomerWallesterAccount::where('customer_id', $customerId)->first();
    }
}

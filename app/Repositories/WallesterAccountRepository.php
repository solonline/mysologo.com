<?php
namespace App\Repositories;

use App\Contracts\WallesterAccountRepositoryInterface;
use App\Models\CustomerWallesterAccount;

class WallesterAccountRepository implements WallesterAccountRepositoryInterface
{
    public function create(array $data): CustomerWallesterAccount
    {
        return CustomerWallesterAccount::create($data);
    }

    public function findByCustomerId($customerId): ?CustomerWallesterAccount
    {
        return CustomerWallesterAccount::where('customer_id', $customerId)->first();
    }
}

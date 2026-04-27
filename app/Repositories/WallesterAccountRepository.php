<?php
namespace App\Repositories;

use App\Models\CustomerWallesterAccount;

class WallesterAccountRepository
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

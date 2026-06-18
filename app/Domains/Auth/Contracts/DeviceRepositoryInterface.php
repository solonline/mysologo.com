<?php

namespace App\Domains\Auth\Contracts;

use App\Domains\Auth\DTOs\CreatePinDTO;
use App\Models\Customer;
use App\Models\CustomerDevice;

interface DeviceRepositoryInterface
{
    public function findByTokenAndEmail(string $deviceToken, string $email): ?CustomerDevice;

    public function findByCustomerAndToken(Customer $customer, string $deviceToken): ?CustomerDevice;

    public function save(CustomerDevice $device): void;

    public function upsertPin(Customer $customer, CreatePinDTO $dto): CustomerDevice;
}

<?php

namespace App\Domains\Auth\Repositories;

use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Domains\Auth\DTOs\CreatePinDTO;
use App\Models\Customer;
use App\Models\CustomerDevice;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function findByTokenAndEmail(string $deviceToken, string $email): ?CustomerDevice
    {
        return CustomerDevice::where('device_token', $deviceToken)
            ->whereHas('customer', fn ($q) => $q->where('email', $email))
            ->first();
    }

    public function findByCustomerAndToken(Customer $customer, string $deviceToken): ?CustomerDevice
    {
        return CustomerDevice::where('customer_id', $customer->id)
            ->where('device_token', $deviceToken)
            ->first();
    }

    public function save(CustomerDevice $device): void
    {
        $device->save();
    }

    public function upsertPin(Customer $customer, CreatePinDTO $dto): CustomerDevice
    {
        return $customer->devices()->updateOrCreate(
            ['device_token' => $dto->deviceToken],
            [
                'device_name' => $dto->deviceName,
                'pin' => $dto->pin,
            ],
        );
    }
}

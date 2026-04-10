<?php

namespace App\Repositories;

use App\Contracts\DeviceRepositoryInterface;
use App\DTOs\CreatePinDTO;
use App\Models\Customer;
use App\Models\CustomerDevice;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function findByTokenAndEmail(string $deviceToken, string $email): ?CustomerDevice
    {
        return CustomerDevice::where('device_token', $deviceToken)
            ->whereHas('customer', fn($q) => $q->where('email', $email))
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
                'pin'         => $dto->pin,
            ]
        );
    }
}

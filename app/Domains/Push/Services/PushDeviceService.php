<?php

namespace App\Domains\Push\Services;

use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerDevice;

class PushDeviceService
{
    public function __construct(
        protected DeviceRepositoryInterface $devices,
    ) {}

    public function register(Customer $customer, string $deviceToken, string $platform, ?string $pushToken): CustomerDevice
    {
        $device = $this->devices->findByCustomerAndToken($customer, $deviceToken);

        if ($device === null) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Dispositivo não encontrado.',
            );
        }

        $pushToken = $pushToken !== null && $pushToken !== '' ? $pushToken : null;

        $device->fill([
            'platform' => $platform,
            'push_token' => $pushToken,
            'push_enabled' => $pushToken !== null,
            'push_token_seen_at' => now(),
        ]);
        $device->save();

        return $device;
    }

    public function disable(Customer $customer, string $deviceToken): CustomerDevice
    {
        $device = $this->devices->findByCustomerAndToken($customer, $deviceToken);

        if ($device === null) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Dispositivo não encontrado.',
            );
        }

        $device->fill([
            'push_token' => null,
            'push_enabled' => false,
        ]);
        $device->save();

        return $device;
    }

}

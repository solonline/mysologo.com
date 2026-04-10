<?php
namespace App\Services;

use App\Contracts\DeviceRepositoryInterface;
use App\DTOs\CreatePinDTO;
use App\Models\Customer;
use App\Models\CustomerDevice;

class DeviceAuthService
{
    const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        protected DeviceRepositoryInterface $deviceRepository,
    ) {}

    public function createPin(Customer $customer, CreatePinDTO $dto): CustomerDevice
    {
        $hashedDto = new CreatePinDTO(
            deviceToken: $dto->deviceToken,
            deviceName: $dto->deviceName,
            pin: \Illuminate\Support\Facades\Hash::make($dto->pin),
        );

        return $this->deviceRepository->upsertPin($customer, $hashedDto);
    }

    /**
     * Incrementa tentativas e bloqueia se necessário
     */
    public function registerFailedAttempt(CustomerDevice $device): void
    {
        $device->failed_attempts = ($device->failed_attempts ?? 0) + 1;
        if ($device->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $device->blocked_at = now();
        }
    }

    /**
     * Reseta tentativas ao sucesso
     */
    public function resetAttempts(CustomerDevice $device): void
    {
        $device->failed_attempts = 0;
        $device->blocked_at = null;
    }

    /**
     * Verifica se o device está bloqueado
     */
    public function isBlocked(CustomerDevice $device): bool
    {
        return !is_null($device->blocked_at);
    }
}

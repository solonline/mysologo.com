<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Domains\Auth\DTOs\CreatePinDTO;
use App\Domains\Auth\DTOs\ResetPinDTO;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\Hash;

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
            pin: Hash::make($dto->pin),
        );

        return $this->deviceRepository->upsertPin($customer, $hashedDto);
    }

    public function resetPin(Customer $customer, ResetPinDTO $dto): CustomerDevice
    {
        $device = $this->deviceRepository->findByCustomerAndToken($customer, $dto->deviceToken);

        if (! $device || ! $device->pin) {
            throw new ApiException(
                ErrorCode::PIN_NOT_CONFIGURED,
                422,
                'Não existe PIN configurado para este dispositivo.',
            );
        }

        if ($dto->password !== null) {
            if (! Hash::check($dto->password, (string) $customer->password)) {
                throw new ApiException(
                    ErrorCode::INVALID_CREDENTIALS,
                    401,
                    'Password inválida.',
                );
            }
        } else {
            $this->verifyPin($customer, $dto->deviceToken, (string) $dto->currentPin);
        }

        if (Hash::check($dto->pin, (string) $device->pin)) {
            throw new ApiException(
                ErrorCode::INVALID_PIN,
                422,
                'O novo PIN deve ser diferente do PIN actual.',
            );
        }

        $device->pin = Hash::make($dto->pin);
        $this->resetAttempts($device);
        $device->last_used_at = now();
        $this->deviceRepository->save($device);

        return $device;
    }

    public function registerFailedAttempt(CustomerDevice $device): void
    {
        $device->failed_attempts = ($device->failed_attempts ?? 0) + 1;
        if ($device->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $device->blocked_at = now();
        }
    }

    public function resetAttempts(CustomerDevice $device): void
    {
        $device->failed_attempts = 0;
        $device->blocked_at = null;
    }

    public function isBlocked(CustomerDevice $device): bool
    {
        return ! is_null($device->blocked_at);
    }

    public function hasPin(Customer $customer): bool
    {
        return $customer->devices()->whereNotNull('pin')->exists();
    }

    public function verifyPin(Customer $customer, string $deviceToken, string $pin): void
    {
        if (! $this->hasPin($customer)) {
            throw new ApiException(
                ErrorCode::PIN_NOT_CONFIGURED,
                422,
                'É necessário configurar um PIN antes de realizar transferências.',
            );
        }

        $device = $this->deviceRepository->findByCustomerAndToken($customer, $deviceToken);

        if (! $device || ! $device->pin) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Dispositivo não encontrado.',
            );
        }

        if ($this->isBlocked($device)) {
            throw new ApiException(
                ErrorCode::DEVICE_BLOCKED,
                423,
                'PIN bloqueado. Faça login convencional para desbloquear.',
            );
        }

        if (! Hash::check($pin, $device->pin)) {
            $this->registerFailedAttempt($device);
            $this->deviceRepository->save($device);

            throw new ApiException(
                ErrorCode::INVALID_PIN,
                401,
                'PIN inválido.',
            );
        }

        $this->resetAttempts($device);
        $device->last_used_at = now();
        $this->deviceRepository->save($device);
    }
}

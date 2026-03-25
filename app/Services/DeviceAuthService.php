<?php
namespace App\Services;

use App\Models\CustomerDevice;

class DeviceAuthService
{
    const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Incrementa tentativas e bloqueia se necessário
     */
    public function registerFailedAttempt(CustomerDevice $device): void
    {
        $device->failed_attempts = ($device->failed_attempts ?? 0) + 1;
        if ($device->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $device->blocked_at = now();
        }
        $device->save();
    }

    /**
     * Reseta tentativas ao sucesso
     */
    public function resetAttempts(CustomerDevice $device): void
    {
        $device->failed_attempts = 0;
        $device->blocked_at = null;
        $device->save();
    }

    /**
     * Verifica se o device está bloqueado
     */
    public function isBlocked(CustomerDevice $device): bool
    {
        return !is_null($device->blocked_at);
    }
}

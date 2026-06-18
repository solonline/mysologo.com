<?php

namespace App\Domains\Onboarding\Enums;

/**
 * Estado operacional de uma conta num provedor externo (Stripe, Wallester, Ebury).
 */
enum IntegrationAccountStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case RESTRICTED = 'RESTRICTED';
    case SUSPENDED = 'SUSPENDED';
    case FAILED = 'FAILED';
    case CLOSED = 'CLOSED';

    public function isOperational(): bool
    {
        return in_array($this, [self::ACTIVE, self::RESTRICTED], true);
    }

    /** Bloqueio total imposto pelo terceiro — sem operações. */
    public function isBlocked(): bool
    {
        return in_array($this, [self::SUSPENDED, self::FAILED, self::CLOSED], true);
    }

    public function allowsDeposits(): bool
    {
        return in_array($this, [self::ACTIVE, self::RESTRICTED], true);
    }

    public function allowsWithdrawals(): bool
    {
        return $this === self::ACTIVE;
    }

    /** @return list<string> */
    public function blockedActionsFor(IntegrationProvider $provider): array
    {
        if ($this === self::ACTIVE) {
            return [];
        }

        $actions = match ($provider) {
            IntegrationProvider::STRIPE => ['top_up', 'receive_payments'],
            IntegrationProvider::WALLESTER => ['withdraw', 'card_payments'],
            IntegrationProvider::EBURY => ['receive_transfers', 'fx'],
        };

        return match ($this) {
            self::RESTRICTED => match ($provider) {
                IntegrationProvider::STRIPE => ['top_up'],
                IntegrationProvider::WALLESTER => ['withdraw'],
                IntegrationProvider::EBURY => ['fx'],
            },
            default => $actions,
        };
    }

    public static function fromLegacy(string $legacy): self
    {
        return match (strtolower($legacy)) {
            'active' => self::ACTIVE,
            'inactive' => self::SUSPENDED,
            'blocked' => self::SUSPENDED,
            'pending' => self::PENDING,
            default => self::tryFrom(strtoupper($legacy)) ?? self::PENDING,
        };
    }
}

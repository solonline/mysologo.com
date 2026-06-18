<?php

namespace App\Domains\Onboarding\Enums;

/**
 * Ciclo de vida da conta na plataforma Yoop.
 * ACTIVE exige aprovação de Ebury ou Wallester + Stripe; bloqueio só se ambos recusarem.
 */
enum CustomerLifecycleStatus: string
{
    case PENDING_EMAIL_VERIFICATION = 'PENDING_EMAIL_VERIFICATION';
    case PENDING_PHONE_VERIFICATION = 'PENDING_PHONE_VERIFICATION';
    case PENDING_IDENTITY_VERIFICATION = 'PENDING_IDENTITY_VERIFICATION';
    case IDENTITY_UNDER_REVIEW = 'IDENTITY_UNDER_REVIEW';
    case IDENTITY_REJECTED = 'IDENTITY_REJECTED';
    case PROVISIONING = 'PROVISIONING';
    case PENDING_EBURY_ONBOARDING = 'PENDING_EBURY_ONBOARDING';
    case EBURY_UNDER_REVIEW = 'EBURY_UNDER_REVIEW';
    case EBURY_REJECTED = 'EBURY_REJECTED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case BLOCKED = 'BLOCKED';

    public function isPlatformActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /** Conta pode movimentar valores na plataforma. */
    public function canTransact(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isGloballyBlocked(): bool
    {
        return in_array($this, [self::SUSPENDED, self::BLOCKED], true);
    }

    /** Estado negativo — recusa ou bloqueio com motivo exposto ao customer. */
    public function isNegative(): bool
    {
        return in_array($this, self::negativeStatuses(), true);
    }

    /** @return array<int, self> */
    public static function negativeStatuses(): array
    {
        return [
            self::IDENTITY_REJECTED,
            self::SUSPENDED,
            self::BLOCKED,
        ];
    }

    /** @return array<int, self> */
    public static function pendingStatuses(): array
    {
        return [
            self::PENDING_EMAIL_VERIFICATION,
            self::PENDING_PHONE_VERIFICATION,
            self::PENDING_IDENTITY_VERIFICATION,
            self::IDENTITY_UNDER_REVIEW,
            self::PROVISIONING,
            self::PENDING_EBURY_ONBOARDING,
            self::EBURY_UNDER_REVIEW,
        ];
    }

    public static function fromLegacy(string $legacy): self
    {
        return match (strtoupper($legacy)) {
            'PENDING' => self::PENDING_EMAIL_VERIFICATION,
            'ANALYSIS', 'ANALYSING' => self::IDENTITY_UNDER_REVIEW,
            'ACTIVE' => self::ACTIVE,
            'INACTIVE' => self::SUSPENDED,
            'BLOCKED' => self::BLOCKED,
            default => self::tryFrom(strtoupper($legacy)) ?? self::PENDING_EMAIL_VERIFICATION,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING_EMAIL_VERIFICATION => 'Aguardando e-mail',
            self::PENDING_PHONE_VERIFICATION => 'Aguardando telefone',
            self::PENDING_IDENTITY_VERIFICATION => 'Aguardando identidade',
            self::IDENTITY_UNDER_REVIEW => 'Identidade em análise',
            self::IDENTITY_REJECTED => 'Identidade recusada',
            self::PROVISIONING => 'Provisionando contas',
            self::PENDING_EBURY_ONBOARDING => 'Aguardando documentos Ebury',
            self::EBURY_UNDER_REVIEW => 'Ebury em análise',
            self::EBURY_REJECTED => 'Recusado pela Ebury',
            self::ACTIVE => 'Ativo',
            self::SUSPENDED => 'Suspenso',
            self::BLOCKED => 'Bloqueado',
        };
    }
}

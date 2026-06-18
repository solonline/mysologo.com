<?php

namespace App\Domains\Onboarding\DTOs;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;

/**
 * Estado de onboarding/verificação de um customer.
 * Independente do provedor de KYC (Didit hoje, outro amanhã).
 */
readonly class OnboardingStatus
{
    public function __construct(
        public bool $emailVerified,
        public bool $phoneVerified,
        public string $identityStatus,
        public ?array $identitySession,
        public bool $requiresResponsible,
        public CustomerLifecycleStatus $lifecycleStatus,
        public ?string $statusReason,
        public array $capabilities,
        public array $integrations,
        public ?string $message = null,
    ) {}

    /**
     * Formato estruturado (v2) — preferido para novas integrações.
     */
    public function toArray(): array
    {
        return [
            'lifecycle' => [
                'status' => $this->lifecycleStatus->value,
                'label' => $this->lifecycleStatus->label(),
                'is_negative' => $this->lifecycleStatus->isNegative(),
                'reason' => $this->statusReason,
            ],
            'capabilities' => $this->capabilities,
            'integrations' => $this->integrations,
            'onboarding' => [
                'requires_responsible' => $this->requiresResponsible,
                'message' => $this->message,
            ],
            'verification' => [
                'email' => $this->emailVerified,
                'phone' => $this->phoneVerified,
                'identity' => $this->identityStatus,
            ],
            'identity_verification' => [
                'status' => $this->identitySession['status'] ?? $this->identityStatus,
                'session' => $this->identitySession,
            ],
        ];
    }

    /**
     * Formato plano legado (v1) — manter até o front migrar.
     *
     * @deprecated Remover na troca final de rotas/resources.
     */
    public function toLegacyArray(): array
    {
        return [
            'didit_session' => $this->identitySession,
            'requires_responsible' => $this->requiresResponsible,
            'kyc_status' => $this->identityStatus,
            'email_verified' => $this->emailVerified,
            'document_verified' => $this->identityStatus,
            'phone_verified' => $this->phoneVerified,
            'lifecycle_status' => $this->lifecycleStatus->value,
            'status_reason' => $this->statusReason,
            'message' => $this->message,
        ];
    }
}

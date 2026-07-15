<?php

namespace App\Domains\Onboarding\DTOs;

use App\Domains\Kyc\Enums\DocumentStatus;
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
     * Payload de identity_verification para respostas de auth (login).
     * Omite sessão quando o KYC já concluiu ou a sessão não exige acção do utilizador.
     *
     * @return array{status: string, session: array<string, mixed>|null}|null
     */
    public function identityVerificationForAuth(): ?array
    {
        if ($this->isIdentityComplete()) {
            return null;
        }

        $sessionStatus = (string) ($this->identitySession['status'] ?? $this->identityStatus);

        if ($this->isTerminalSessionStatus($sessionStatus) || $sessionStatus === 'In Review') {
            return [
                'status' => $sessionStatus,
                'session' => null,
            ];
        }

        if (! $this->hasActionableSession()) {
            return [
                'status' => $sessionStatus,
                'session' => null,
            ];
        }

        return [
            'status' => $sessionStatus,
            'session' => $this->identitySession,
        ];
    }

    private function isIdentityComplete(): bool
    {
        return strtolower($this->identityStatus) === strtolower(DocumentStatus::APPROVED->value);
    }

    private function isTerminalSessionStatus(string $status): bool
    {
        return in_array($status, ['Approved', 'Expired', 'Abandoned'], true);
    }

    private function hasActionableSession(): bool
    {
        if (! is_array($this->identitySession)) {
            return false;
        }

        return filled($this->identitySession['session_id'] ?? null)
            || filled($this->identitySession['session_url'] ?? null);
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

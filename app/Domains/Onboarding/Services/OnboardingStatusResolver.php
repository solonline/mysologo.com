<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Onboarding\DTOs\OnboardingStatus;
use App\Models\Customer;
use App\Domains\Kyc\Services\IdentityVerificationService;

/**
 * Resolve o estado de onboarding de um customer.
 *
 * Auth apenas autentica; este serviço concentra verificação de identidade,
 * telefone, corporate responsible, etc. — sem acoplar nomes ao provedor KYC.
 */
class OnboardingStatusResolver
{
    public function __construct(
        protected IdentityVerificationService $identityVerification,
        protected PhoneVerificationService $phoneVerificationService,
        protected CustomerLifecycleService $lifecycle,
        protected IntegrationAccountService $integrations,
    ) {}

    public function resolveForCustomer(Customer $customer): OnboardingStatus
    {
        $identity = $this->resolveIdentityVerification($customer);

        return $this->buildStatus(
            customer: $customer,
            identitySession: $identity['session'],
            requiresResponsible: $identity['requires_responsible'],
            message: $identity['message'],
        );
    }

    public function resolveForPinLogin(Customer $customer): OnboardingStatus
    {
        return $this->buildStatus(
            customer: $customer,
            identitySession: $this->latestIdentitySessionSnapshot($customer),
            requiresResponsible: false,
        );
    }

    private function buildStatus(
        Customer $customer,
        ?array $identitySession,
        bool $requiresResponsible,
        ?string $message = null,
    ): OnboardingStatus {
        return new OnboardingStatus(
            emailVerified: $customer->hasVerifiedEmail(),
            phoneVerified: $this->isPhoneVerified($customer),
            identityStatus: (string) $customer->doc_status,
            identitySession: $identitySession,
            requiresResponsible: $requiresResponsible,
            lifecycleStatus: $this->lifecycle->current($customer),
            statusReason: $customer->status_reason,
            capabilities: $this->resolveCapabilities($customer),
            integrations: $this->resolveIntegrations($customer),
            message: $message,
        );
    }

    private function resolveCapabilities(Customer $customer): array
    {
        return $this->integrations->resolveCapabilities($customer);
    }

    private function resolveIntegrations(Customer $customer): array
    {
        return array_map(
            fn ($state) => $state->toArray(),
            $this->integrations->resolveStates($customer),
        );
    }

    private function resolveIdentityVerification(Customer $customer): array
    {
        return match ($customer->type) {
            'corporate' => $this->resolveCorporateIdentity($customer),
            'retail' => $this->resolveRetailIdentity($customer),
            default => $this->emptyIdentityResult(),
        };
    }

    private function resolveRetailIdentity(Customer $customer): array
    {
        $result = $this->identityVerification->ensureValidIndividualDocument($customer);

        return [
            'requires_responsible' => false,
            'session' => $result['session'] ?? null,
            'message' => $result['message'] ?? null,
        ];
    }

    private function resolveCorporateIdentity(Customer $customer): array
    {
        $responsible = $customer->responsibles()->first();

        if ($responsible === null) {
            return [
                'requires_responsible' => true,
                'session' => null,
                'message' => 'A legal representative must be registered before identity verification.',
            ];
        }

        $result = $this->identityVerification->ensureValidIndividualDocument($responsible);

        return [
            'requires_responsible' => false,
            'session' => $result['session'] ?? null,
            'message' => $result['message'] ?? null,
        ];
    }

    private function latestIdentitySessionSnapshot(Customer $customer): array
    {
        $session = $customer->diditSessions()->latest()->first();

        if ($session === null) {
            return ['status' => $customer->doc_status];
        }

        return ['status' => $session->status];
    }

    private function isPhoneVerified(Customer $customer): bool
    {
        if (!$customer->phone) {
            return false;
        }

        return $this->phoneVerificationService->isVerified($customer, $customer->phone);
    }

    private function emptyIdentityResult(): array
    {
        return [
            'requires_responsible' => false,
            'session' => null,
            'message' => null,
        ];
    }
}

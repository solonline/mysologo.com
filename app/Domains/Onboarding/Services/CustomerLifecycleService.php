<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Events\CustomerApproved;
use App\Models\Customer;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerWallesterAccount;

class CustomerLifecycleService
{
    public function current(Customer $customer): CustomerLifecycleStatus
    {
        $status = $customer->status;

        if ($status instanceof CustomerLifecycleStatus) {
            return $status;
        }

        return CustomerLifecycleStatus::fromLegacy((string) $status);
    }

    public function transitionTo(Customer $customer, CustomerLifecycleStatus $status, ?string $reason = null): Customer
    {
        $customer->status = $status;
        $customer->status_reason = match (true) {
            $status->isNegative(), $status->isGloballyBlocked() => $reason,
            in_array($status, [
                CustomerLifecycleStatus::PENDING_EBURY_ONBOARDING,
                CustomerLifecycleStatus::EBURY_UNDER_REVIEW,
                CustomerLifecycleStatus::PROVISIONING,
            ], true) => $reason,
            default => null,
        };
        $customer->save();

        return $customer;
    }

    public function initialStatusForRegistration(): CustomerLifecycleStatus
    {
        return CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION;
    }

    public function canUsePlatform(Customer $customer): bool
    {
        return ! $this->current($customer)->isGloballyBlocked();
    }

    public function canTransact(Customer $customer): bool
    {
        return $this->current($customer)->canTransact();
    }

    public function hasApprovedFundingPartner(Customer $customer): bool
    {
        return $this->integrationActive($customer->eburyAccount)
            || $this->integrationActive($customer->wallesterAccounts);
    }

    public function beginEburyOnboarding(Customer $customer, ?string $reason = null): Customer
    {
        return $this->transitionTo(
            $customer,
            CustomerLifecycleStatus::PENDING_EBURY_ONBOARDING,
            $reason ?? 'Aguardando submissão e aprovação dos documentos Ebury.',
        );
    }

    public function markEburyUnderReview(Customer $customer, ?string $reason = null): Customer
    {
        return $this->transitionTo(
            $customer,
            CustomerLifecycleStatus::EBURY_UNDER_REVIEW,
            $reason ?? 'Documentos em análise pela Ebury.',
        );
    }

    public function syncFromEburyAccount(CustomerEburyAccount $account): void
    {
        $customer = $account->customer()->first();

        if (! $customer) {
            return;
        }

        $status = $this->normalizeIntegrationStatus($account->status);

        match ($status) {
            IntegrationAccountStatus::PENDING => $this->handleEburyPending($customer, $account),
            default => $this->evaluateActivation($customer),
        };
    }

    public function syncFromWallesterAccount(CustomerWallesterAccount $account): void
    {
        $customer = $account->customer()->first();

        if (! $customer) {
            return;
        }

        $this->evaluateActivation($customer);
    }

    public function evaluateActivation(Customer $customer): void
    {
        $customer = $customer->fresh(['eburyAccount', 'wallesterAccounts', 'stripeAccount']);

        if ($this->areBothFundingPartnersRejected($customer)) {
            $this->transitionTo(
                $customer,
                CustomerLifecycleStatus::BLOCKED,
                'Ebury e Wallester recusaram a conta. Não é possível continuar.',
            );

            return;
        }

        if (! $this->hasApprovedFundingPartner($customer)) {
            $this->transitionTo(
                $customer,
                CustomerLifecycleStatus::PROVISIONING,
                $this->pendingPartnerReason($customer),
            );

            return;
        }

        if (! $this->integrationActive($customer->stripeAccount)) {
            $this->transitionTo(
                $customer,
                CustomerLifecycleStatus::PROVISIONING,
                'Parceiro financeiro aprovado. Provisionando Stripe.',
            );
            app(FinancialProvisioningService::class)->provisionStripe($customer->fresh());
            $customer->refresh();
        }

        if (! $this->hasApprovedFundingPartner($customer)) {
            return;
        }

        if (! $this->integrationActive($customer->stripeAccount)) {
            return;
        }

        $wasActive = $this->current($customer) === CustomerLifecycleStatus::ACTIVE;
        $this->transitionTo($customer, CustomerLifecycleStatus::ACTIVE);

        if (! $wasActive) {
            CustomerApproved::dispatch($customer);
        }
    }

    /** @deprecated Use evaluateActivation() */
    public function tryActivate(Customer $customer): void
    {
        $this->evaluateActivation($customer);
    }

    private function handleEburyPending(Customer $customer, CustomerEburyAccount $account): void
    {
        $current = $this->current($customer);

        if (in_array($current, [
            CustomerLifecycleStatus::PROVISIONING,
            CustomerLifecycleStatus::EBURY_UNDER_REVIEW,
            CustomerLifecycleStatus::ACTIVE,
        ], true)) {
            return;
        }

        $this->beginEburyOnboarding($customer, $account->status_reason);
    }

    private function pendingPartnerReason(Customer $customer): string
    {
        $eburyRejected = $this->isFundingPartnerRejected($customer->eburyAccount?->status);
        $wallesterRejected = $this->isFundingPartnerRejected($customer->wallesterAccounts?->status);

        if ($wallesterRejected && ! $eburyRejected) {
            return 'Falha Wallester. A aguardar intervenção.';
        }

        if ($eburyRejected && ! $wallesterRejected) {
            return 'Ebury recusou. Aguardando decisão Wallester.';
        }

        if ($wallesterRejected && $eburyRejected) {
            return 'Ebury e Wallester recusaram. A aguardar intervenção.';
        }

        return 'Aguardando aprovação Ebury ou Wallester.';
    }

    private function areBothFundingPartnersRejected(Customer $customer): bool
    {
        $eburyStatus = $this->normalizeIntegrationStatus($customer->eburyAccount?->status);
        $wallesterStatus = $this->normalizeIntegrationStatus($customer->wallesterAccounts?->status);

        if ($eburyStatus === null || $wallesterStatus === null) {
            return false;
        }

        return $this->isFundingPartnerRejected($eburyStatus)
            && $this->isFundingPartnerRejected($wallesterStatus);
    }

    private function isFundingPartnerRejected(mixed $status): bool
    {
        $normalized = $this->normalizeIntegrationStatus($status);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, [
            IntegrationAccountStatus::FAILED,
            IntegrationAccountStatus::CLOSED,
            IntegrationAccountStatus::SUSPENDED,
        ], true);
    }

    private function integrationActive(mixed $account): bool
    {
        if ($account === null) {
            return false;
        }

        return $this->normalizeIntegrationStatus($account->status) === IntegrationAccountStatus::ACTIVE;
    }

    private function normalizeIntegrationStatus(mixed $status): ?IntegrationAccountStatus
    {
        if ($status === null) {
            return null;
        }

        if ($status instanceof IntegrationAccountStatus) {
            return $status;
        }

        return IntegrationAccountStatus::fromLegacy((string) $status);
    }
}

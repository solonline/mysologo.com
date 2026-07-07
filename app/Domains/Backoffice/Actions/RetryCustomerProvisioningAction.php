<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Jobs\ProvisionFundingPartnersJob;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class RetryCustomerProvisioningAction
{
    public function __construct(
        protected IntegrationAccountService $integrationAccounts,
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function execute(Customer $customer): Customer
    {
        $customer = $customer->fresh(['wallesterAccounts']);

        if ($this->lifecycle->current($customer) !== CustomerLifecycleStatus::PROVISIONING) {
            throw ValidationException::withMessages([
                'customer' => 'O cliente não está em provisioning.',
            ]);
        }

        $wallester = $customer->wallesterAccount;

        if ($wallester !== null) {
            $status = $wallester->status instanceof IntegrationAccountStatus
                ? $wallester->status
                : IntegrationAccountStatus::fromLegacy((string) $wallester->status);

            if ($status === IntegrationAccountStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'customer' => 'A conta Wallester já está activa.',
                ]);
            }

            if ($status === IntegrationAccountStatus::FAILED) {
                $this->integrationAccounts->updateWallester(
                    $wallester,
                    IntegrationAccountStatus::PENDING,
                    'Retry manual solicitado pelo backoffice.',
                    'backoffice.retry',
                );
            }
        }

        $this->lifecycle->transitionTo(
            $customer,
            CustomerLifecycleStatus::PROVISIONING,
            'Reprocessamento de provisioning solicitado.',
        );

        ProvisionFundingPartnersJob::dispatch($customer->id);

        return $customer->fresh();
    }
}

<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Backoffice\Services\StaffNotificationService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;

class ProvisioningFailureNotifier
{
    public function __construct(
        protected StaffNotificationService $staffNotifications,
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function notifyWallesterFailure(Customer $customer, string $error): void
    {
        $this->lifecycle->transitionTo(
            $customer,
            CustomerLifecycleStatus::PROVISIONING,
            'Falha ao criar conta Wallester. Equipa a analisar.',
        );

        $this->staffNotifications->notifyUsersWithPermission(
            type: 'provisioning.wallester_failed',
            title: 'Falha ao provisionar Wallester',
            permission: 'customers.view',
            body: "Cliente #{$customer->id} — {$customer->name}",
            url: route('dashboard.customers.show', $customer),
            data: [
                'customer_id' => $customer->id,
                'provider' => 'wallester',
                'error' => $error,
            ],
        );
    }
}

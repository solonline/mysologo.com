<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Backoffice\DTOs\UpdateCustomerFromBackofficeData;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Models\Customer;

class UpdateCustomerFromBackofficeAction
{
    public function __construct(
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function execute(Customer $customer, UpdateCustomerFromBackofficeData $data): Customer
    {
        $customer->fill([
            'name' => $data->name,
            'email' => $data->email,
            'currency' => $data->currency,
            'language' => $data->language,
            'doc_status' => $data->docStatus,
        ]);
        $customer->save();

        $newStatus = CustomerLifecycleStatus::from($data->status);
        $currentStatus = $this->lifecycle->current($customer);

        if ($newStatus !== $currentStatus) {
            $this->lifecycle->transitionTo(
                $customer,
                $newStatus,
                'Status alterado manualmente no backoffice.',
            );
        }

        return $customer->fresh();
    }
}

<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Backoffice\DTOs\CreateCustomerFromBackofficeData;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\DTOs\RegisterCustomerDTO;
use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Settings\Services\SettingsService;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateCustomerFromBackofficeAction
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected SettingsService $settings,
        protected CustomerProfileService $profile,
    ) {}

    public function execute(CreateCustomerFromBackofficeData $data): Customer
    {
        $dto = new RegisterCustomerDTO(
            email: $data->email,
            password: Hash::make($data->password),
            phone: $data->phone,
            type: $data->type,
            username: $data->username,
            name: $data->name,
            language: $data->language,
            currency: $data->currency,
        );

        return DB::transaction(function () use ($dto) {
            $customer = $this->customerRepository->createCustomer($dto);
            $validationUrl = $this->profile->generateEmailValidationUrl($customer);

            UserRegistered::dispatch($customer, $validationUrl, $this->settings->getInfoSystem());

            return $customer;
        });
    }
}

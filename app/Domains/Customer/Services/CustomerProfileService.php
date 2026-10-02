<?php

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Domains\Customer\DTOs\CreateResponsibleDTO;
use App\Domains\Customer\DTOs\UpdateCustomerDTO;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class CustomerProfileService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected IdentityVerificationService $identityVerification,
    ) {}

    /** @return array{responsible: Customer, didit_session: mixed} */
    public function updateOrCreateResponsible(Customer $customer, CreateResponsibleDTO $dto): array
    {
        if ($customer->type !== 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                403,
                'Only corporate customers can have responsible persons.'
            );
        }

        return DB::transaction(function () use ($customer, $dto) {
            $responsible = $customer->responsibles()->first();

            if (! $responsible) {
                $responsible = $this->customerRepository->createResponsible($customer, $dto);
            }

            $this->identityVerification->ensureValidIndividualDocument($responsible);

            return [
                'responsible' => $responsible,
                'didit_session' => $responsible->diditSessions()->latest()->first(),
            ];
        });
    }

    public function findByUsername(string $username, array $filters = [], ?int $excludeId = null): Customer
    {
        $customer = $this->customerRepository->findByUsername($username, $filters, $excludeId);

        if (! $customer) {
            throw new ApiException(
                ErrorCode::CUSTOMER_NOT_FOUND,
                404,
                'Customer not found.'
            );
        }

        return $customer;
    }

    public function update(Customer $customer, UpdateCustomerDTO $dto): Customer
    {
        return $this->customerRepository->updateCustomer($customer, $dto);
    }

    public function sendValidationEmail(Customer $customer, string $validationUrl): void
    {
        Mail::to($customer->email)->send(new \App\Domains\Auth\Mail\ValidationEmail($customer, $validationUrl));
    }

    public function generateEmailValidationUrl(Customer $customer): string
    {
        $frontendBase = rtrim((string) (config('app.url_frontend') ?: config('app.url')), '/');

        URL::forceRootUrl($frontendBase);

        try {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $customer->id,
                    'hash' => sha1($customer->email),
                ]
            );
        } finally {
            URL::forceRootUrl(null);
        }
    }
}

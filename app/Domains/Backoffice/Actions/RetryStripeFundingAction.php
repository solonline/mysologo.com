<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Domains\Stripe\Services\StripeService;
use App\Exceptions\ApiException;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class RetryStripeFundingAction
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    public function execute(Customer $customer): Customer
    {
        $customer = $customer->fresh('stripeAccount');
        $account = $customer->stripeAccount;

        if ($account === null) {
            throw ValidationException::withMessages([
                'customer' => 'Cliente sem conta Stripe associada.',
            ]);
        }

        if ($account->funding_status === StripeFundingStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'customer' => 'O IBAN de funding já está activo.',
            ]);
        }

        if (! $account->funding_status?->canRetry()) {
            throw ValidationException::withMessages([
                'customer' => 'Não é possível repetir a criação do IBAN neste estado.',
            ]);
        }

        try {
            $this->stripeService->createCustomerFundingInstructions($customer);
        } catch (ApiException $exception) {
            throw ValidationException::withMessages([
                'customer' => $exception->getMessage(),
            ]);
        }

        return $customer->fresh('stripeAccount');
    }
}

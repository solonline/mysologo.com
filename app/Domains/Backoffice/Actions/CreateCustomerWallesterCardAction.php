<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Wallester\Services\WallesterService;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\CustomerWallesterCard;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateCustomerWallesterCardAction
{
    public function __construct(
        protected WallesterService $wallesterService,
    ) {}

    public function execute(Customer $customer): CustomerWallesterCard
    {
        $customer = $customer->fresh('wallesterAccount');

        if ($customer->wallesterAccount === null) {
            throw ValidationException::withMessages([
                'customer' => 'Cliente sem conta Wallester associada.',
            ]);
        }

        $name = trim((string) $customer->name);

        try {
            return $this->wallesterService->createCardForCustomer(
                $customer,
                [
                    'type' => 'Virtual',
                    'name' => $name !== '' ? mb_substr($name, 0, 255) : 'Virtual Card',
                ],
                allowAdditionalCards: true,
            );
        } catch (ApiException|RuntimeException $exception) {
            throw ValidationException::withMessages([
                'customer' => $exception->getMessage(),
            ]);
        }
    }
}

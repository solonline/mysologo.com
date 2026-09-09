<?php

namespace App\Domains\Backoffice\Actions;

use App\Domains\Wallester\Services\WallesterService;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\CustomerWallesterCard;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LinkCustomerWallesterCardAction
{
    public function __construct(
        protected WallesterService $wallesterService,
    ) {}

    public function execute(Customer $from, CustomerWallesterCard $card, string $targetCustomer): CustomerWallesterCard
    {
        $card->loadMissing('wallesterAccount');

        if ($card->wallesterAccount?->customer_id !== $from->id) {
            throw ValidationException::withMessages([
                'card' => 'Este cartão não pertence a este cliente.',
            ]);
        }

        $to = $this->resolveTargetCustomer($targetCustomer);

        try {
            return $this->wallesterService->linkCardToAnotherCustomer($from, $card, $to);
        } catch (ApiException|RuntimeException $exception) {
            throw ValidationException::withMessages([
                'target_customer' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveTargetCustomer(string $identifier): Customer
    {
        $identifier = trim($identifier);
        $username = ltrim($identifier, '@');

        $customer = null;

        if (ctype_digit($identifier)) {
            $customer = Customer::query()->find((int) $identifier);
        }

        if ($customer === null && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $customer = Customer::query()->where('email', $identifier)->first();
        }

        if ($customer === null && $username !== '') {
            $customer = Customer::query()->where('username', $username)->first();
        }

        if ($customer === null) {
            throw ValidationException::withMessages([
                'target_customer' => 'Cliente de destino não encontrado.',
            ]);
        }

        return $customer;
    }
}

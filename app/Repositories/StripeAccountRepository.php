<?php

namespace App\Repositories;

use App\Contracts\StripeAccountRepositoryInterface;
use App\DTOs\CreateStripeAccountDTO;
use App\Models\CustomerStripeAccount;

class StripeAccountRepository implements StripeAccountRepositoryInterface
{
    public function create(CreateStripeAccountDTO $dto): CustomerStripeAccount
    {
        return CustomerStripeAccount::create([
            'customer_id'        => $dto->customerId,
            'stripe_customer_id' => $dto->stripeCustomerId,
            'currency'           => $dto->currency,
        ]);
    }

    public function getStripeId(int $customerId): ?string
    {
        return CustomerStripeAccount::where('customer_id', $customerId)->first()?->stripe_customer_id;
    }
}

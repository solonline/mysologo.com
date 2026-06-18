<?php

namespace App\Domains\Stripe\Contracts;

use App\Domains\Stripe\DTOs\CreateStripeAccountDTO;
use App\Models\CustomerStripeAccount;

interface StripeAccountRepositoryInterface
{
    public function create(CreateStripeAccountDTO $dto): CustomerStripeAccount;
}

<?php

namespace App\Contracts;

use App\DTOs\CreateStripeAccountDTO;
use App\Models\CustomerStripeAccount;

interface StripeAccountRepositoryInterface
{
    public function create(CreateStripeAccountDTO $dto): CustomerStripeAccount;
}

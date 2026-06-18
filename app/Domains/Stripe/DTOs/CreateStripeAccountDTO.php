<?php

namespace App\Domains\Stripe\DTOs;

class CreateStripeAccountDTO
{
    public function __construct(
        public int $customerId,
        public string $stripeCustomerId,
        public string $currency = 'EUR',
    ) {}
}

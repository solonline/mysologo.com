<?php

namespace App\Domains\Stripe\Repositories;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeAccountRepositoryInterface;
use App\Domains\Stripe\DTOs\CreateStripeAccountDTO;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\CustomerStripeAccount;

class StripeAccountRepository implements StripeAccountRepositoryInterface
{
    public function create(CreateStripeAccountDTO $dto): CustomerStripeAccount
    {
        return CustomerStripeAccount::create([
            'customer_id' => $dto->customerId,
            'stripe_customer_id' => $dto->stripeCustomerId,
            'currency' => $dto->currency,
            'status' => IntegrationAccountStatus::ACTIVE,
            'funding_status' => StripeFundingStatus::PENDING,
            'funding_status_changed_at' => now(),
        ]);
    }

    public function getStripeId(int $customerId): ?string
    {
        return CustomerStripeAccount::where('customer_id', $customerId)->first()?->stripe_customer_id;
    }
}

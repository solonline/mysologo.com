<?php

namespace App\Domains\Onboarding\Contracts;

use App\Models\Customer;
use App\Models\PhoneVerification;

interface PhoneVerificationRepositoryInterface
{
    public function createOrUpdate(Customer $customer, string $phone, string $code): PhoneVerification;

    public function verify(Customer $customer, string $phone, string $code): bool;

    public function isVerified(Customer $customer, string $phone): bool;
}

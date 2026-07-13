<?php

namespace App\Domains\Kyc\Contracts;

use App\Models\Customer;
use App\Models\CustomerDiditSession;

interface IdentityVerificationSessionRepositoryInterface
{
    public function upsertBySessionId(Customer $customer, string $sessionId, array $data): CustomerDiditSession;

    public function findBySessionId(string $sessionId): ?CustomerDiditSession;

    public function findByApplicationId(string $applicationId): ?CustomerDiditSession;

    public function latestByCustomer(Customer $customer): ?CustomerDiditSession;
}

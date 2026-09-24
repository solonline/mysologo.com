<?php

namespace App\Domains\Kyc\Contracts;

use App\Models\Customer;
use App\Models\CustomerDiditSession;

interface IdentityVerificationSessionRepositoryInterface
{
    public function upsertBySessionId(Customer $customer, string $sessionId, array $data): CustomerDiditSession;

    public function findBySessionId(string $sessionId): ?CustomerDiditSession;

    public function findByVendorData(string $vendorData): ?CustomerDiditSession;

    public function findByVendorUserId(string $vendorUserId): ?CustomerDiditSession;

    public function latestByCustomer(Customer $customer): ?CustomerDiditSession;
}

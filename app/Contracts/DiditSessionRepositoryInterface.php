<?php

namespace App\Contracts;

use App\Models\Customer;
use App\Models\CustomerDiditSession;

interface DiditSessionRepositoryInterface
{
    public function upsertBySessionId(Customer $customer, string $sessionId, array $data): CustomerDiditSession;
    public function findBySessionId(string $sessionId): ?CustomerDiditSession;
    public function latestByCustomer(Customer $customer): ?CustomerDiditSession;
}

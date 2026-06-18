<?php

namespace App\Domains\Kyc\Repositories;

use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Models\Customer;
use App\Models\CustomerDiditSession;

class IdentityVerificationSessionRepository implements IdentityVerificationSessionRepositoryInterface
{
    public function upsertBySessionId(Customer $customer, string $sessionId, array $data): CustomerDiditSession
    {
        return $customer->diditSessions()->updateOrCreate(
            ['session_id' => $sessionId],
            $data
        );
    }

    public function findBySessionId(string $sessionId): ?CustomerDiditSession
    {
        return CustomerDiditSession::where('session_id', $sessionId)->first();
    }

    public function latestByCustomer(Customer $customer): ?CustomerDiditSession
    {
        return $customer->diditSessions()->latest()->first();
    }
}

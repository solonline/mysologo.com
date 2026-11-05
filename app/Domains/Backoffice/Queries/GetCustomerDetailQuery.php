<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\Customer;

class GetCustomerDetailQuery
{
    public function execute(Customer $customer): Customer
    {
        return $customer->load([
            'documents',
            'diditSessions' => fn ($q) => $q->latest(),
            'responsibles',
            'referredBy',
            'balances',
            'wiseTransfers' => fn ($q) => $q
                ->whereNull('settled_at')
                ->whereNull('refunded_at')
                ->orderByDesc('id'),
            'stripeAccount',
            'wallesterAccount.cards' => fn ($q) => $q->orderByDesc('id'),
            'wallesterAccounts',
            'platformWallesterMainAccounts',
            'platformWallesterFeeAccounts',
            'eburyAccount',
            'phoneVerifications',
        ]);
    }
}

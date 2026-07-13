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
            'balances',
            'stripeAccount',
            'wallesterAccount.cards' => fn ($q) => $q->orderByDesc('id'),
            'wallesterAccounts',
            'eburyAccount',
            'phoneVerifications',
            'tpas.paymentMethodFees',
        ]);
    }
}

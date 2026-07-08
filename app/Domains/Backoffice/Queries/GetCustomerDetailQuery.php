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
            'stripeTransactions' => fn ($q) => $q->latest('stripe_created_at')->limit(50),
            'stripeAccount',
            'wallesterAccount',
            'wallesterAccounts',
            'eburyAccount',
            'phoneVerifications',
            'tpas.paymentMethodFees',
        ]);
    }
}

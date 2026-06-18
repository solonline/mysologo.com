<?php

namespace App\Domains\Backoffice\Queries;

use App\Models\Customer;

class GetCustomerDetailQuery
{
    public function execute(Customer $customer): Customer
    {
        return $customer->load([
            'documents',
            'responsibles',
            'balances',
            'transactions' => fn ($q) => $q->latest('stripe_created_at')->limit(50),
            'stripeAccount',
            'wallesterAccounts',
            'eburyAccount',
            'phoneVerifications',
            'tpas.paymentMethodFees',
        ]);
    }
}

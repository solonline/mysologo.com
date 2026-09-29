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
            'stripeAccount',
            'wallesterAccount.cards' => fn ($q) => $q->orderByDesc('id'),
            'wallesterAccounts',
            'platformWallesterMainAccounts',
            'platformWallesterFeeAccounts',
            'eburyAccount',
            'phoneVerifications',
            'tpas.paymentMethodFees',
            'merchants' => fn ($q) => $q->orderByDesc('id'),
            'merchants.paymentMethodFees',
            'merchants.paymentRequests' => fn ($q) => $q
                ->where(function ($requests): void {
                    $requests
                        ->whereHas('stripeTransaction')
                        ->orWhereIn('status', ['pending', 'processing']);
                })
                ->with('stripeTransaction')
                ->latest()
                ->limit(50),
        ]);
    }
}

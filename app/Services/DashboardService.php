<?php

namespace App\Services;

use App\Contracts\TransactionRepositoryInterface;
use App\Models\Customer;

class DashboardService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository
    ) {}

    public function getSummary(Customer $customer): array
    {
        $transactions = $this->transactionRepository->getByCustomer($customer);

        $available = $transactions->where('status', 'succeeded')->where('direction', 'credit')->sum('net_amount')
                   - $transactions->where('status', 'succeeded')->where('direction', 'debit')->sum('net_amount');

        $pending = $transactions->where('status', 'pending')->sum('net_amount');

        return [
            'balance' => [
                'available' => $available,
                'pending'   => $pending,
                'currency'  => 'EUR',
            ],
            'transactions' => $transactions,
        ];
    }
}

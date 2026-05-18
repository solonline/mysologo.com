<?php

namespace App\Services;

use App\Contracts\TransactionRepositoryInterface;
use App\Models\Customer;

class DashboardService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected AccountService $accountService
    ) {}

    public function getSummary(Customer $customer): array
    {
        $balances     = $this->accountService->getBalances($customer);
        $transactions = $this->transactionRepository->getRecent($customer);

        return [
            'balances'     => $balances,
            'transactions' => $transactions,
        ];
    }
}

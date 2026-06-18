<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Models\Customer;

class WalletDashboardService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected WalletAccountService $accountService,
    ) {}

    /** @return array{balances: list<array<string, mixed>>, transactions: mixed} */
    public function getSummary(Customer $customer): array
    {
        return [
            'balances' => $this->accountService->getBalances($customer),
            'transactions' => $this->transactionRepository->getRecent($customer),
        ];
    }
}

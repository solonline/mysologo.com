<?php

namespace App\Repositories;

use App\Contracts\TransactionRepositoryInterface;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function getByCustomer(Customer $customer): Collection
    {
        return Transaction::where('customer_id', $customer->id)
            ->latest()
            ->get();
    }
}

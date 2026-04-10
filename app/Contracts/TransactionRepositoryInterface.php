<?php

namespace App\Contracts;

use App\Models\Customer;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    public function getByCustomer(Customer $customer): Collection;
}

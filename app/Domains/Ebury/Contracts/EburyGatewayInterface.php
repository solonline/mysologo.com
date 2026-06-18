<?php

namespace App\Domains\Ebury\Contracts;

use App\Models\Customer;
use App\Models\CustomerEburyAccount;

interface EburyGatewayInterface
{
    /**
     * Submete o dossiê do customer à API Ebury.
     *
     * @return array{account_id: string, status: string}
     */
    public function submitCustomer(Customer $customer, CustomerEburyAccount $account): array;
}

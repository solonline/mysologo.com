<?php

namespace App\Repositories;

use App\Contracts\CustomerRepositoryInterface;
use App\DTOs\RegisterCustomerDTO;
use App\Models\Customer;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer
    {
        return Customer::create($dto->toArray());
    }
    //
}

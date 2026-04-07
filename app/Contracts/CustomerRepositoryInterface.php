<?php

namespace App\Contracts;

use App\DTOs\RegisterCustomerDTO;
use App\Models\Customer;

interface CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer;
    // Outros métodos de acesso a dados podem ser definidos aqui
}

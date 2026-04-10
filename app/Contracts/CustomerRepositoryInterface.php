<?php

namespace App\Contracts;

use App\DTOs\CreateResponsibleDTO;
use App\DTOs\RegisterCustomerDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Models\Customer;

interface CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer;
    public function findByEmail(string $email): ?Customer;
    public function findById(int $id): ?Customer;
    public function firstResponsible(Customer $corporate): ?Customer;
    public function updateCustomer(Customer $customer, UpdateCustomerDTO $dto): Customer;
    public function createResponsible(Customer $corporate, CreateResponsibleDTO $dto): Customer;
    public function save(Customer $customer): void;
}

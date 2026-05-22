<?php

namespace App\Contracts;

use App\DTOs\CreateResponsibleDTO;
use App\DTOs\RegisterCustomerDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer;
    public function findByEmail(string $email): ?Customer;
    public function findByUsername(string $username, array $filters = [], ?int $excludeId = null): ?Customer;
    public function findById(int $id): ?Customer;
    public function firstResponsible(Customer $corporate): ?Customer;
    public function updateCustomer(Customer $customer, UpdateCustomerDTO $dto): Customer;
    public function createResponsible(Customer $corporate, CreateResponsibleDTO $dto): Customer;
    public function save(Customer $customer): void;
    public function updateAvatar(Customer $customer, string $path): Customer;

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;
    public function getStats(): array;
    public function loadRelations(Customer $customer, array $relations): Customer;
}

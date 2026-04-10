<?php

namespace App\Repositories;

use App\Contracts\CustomerRepositoryInterface;
use App\DTOs\CreateResponsibleDTO;
use App\DTOs\RegisterCustomerDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer
    {
        return Customer::create(array_filter([
            'email'    => $dto->email,
            'password' => $dto->password,
            'type'     => $dto->type,
            'name'     => $dto->name,
            'username' => $dto->username,
        ], fn($v) => !is_null($v)));
    }

    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }

    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    public function firstResponsible(Customer $corporate): ?Customer
    {
        return $corporate->responsibles()->first();
    }

    public function save(Customer $customer): void
    {
        $customer->save();
    }

    public function updateCustomer(Customer $customer, UpdateCustomerDTO $dto): Customer
    {
        $data = array_filter([
            'name'               => $dto->name,
            'username'           => $dto->username,
            'avatar'             => $dto->avatar,
            'email'              => $dto->email,
            'phone'              => $dto->phone,
            'referal_email_code' => $dto->referalEmailCode,
            'password'           => $dto->password ? Hash::make($dto->password) : null,
        ], fn($v) => !is_null($v));

        $customer->update($data);
        return $customer->fresh();
    }

    public function createResponsible(Customer $corporate, CreateResponsibleDTO $dto): Customer
    {
        return Customer::create([
            'username'     => $corporate->username . '_responsible_' . uniqid(),
            'name'         => $dto->name,
            'email'        => $dto->email,
            'corporate_id' => $corporate->id,
            'type'         => 'responsible',
        ]);
    }
}

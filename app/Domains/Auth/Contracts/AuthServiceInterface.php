<?php

namespace App\Domains\Auth\Contracts;

use App\Domains\Auth\DTOs\LoginCustomerDTO;
use App\Domains\Auth\DTOs\LoginWithPinDTO;
use App\Domains\Customer\DTOs\RegisterCustomerDTO;
use App\Models\Customer;

interface AuthServiceInterface
{
    public function register(RegisterCustomerDTO $dto);

    public function login(LoginCustomerDTO $dto, bool $isRegister = false);

    public function loginWithPin(LoginWithPinDTO $dto);

    public function logout(Customer $customer);
}

<?php

namespace App\Contracts;

use App\DTOs\RegisterCustomerDTO;

interface AuthServiceInterface
{
    public function register(RegisterCustomerDTO $dto);
    // Outras assinaturas podem ser adicionadas depois
}

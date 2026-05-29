<?php

namespace App\Contracts;

use App\DTOs\RegisterMerchantDTO;
use App\Models\Merchant;

interface MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant;
    public function isSlugTaken(string $slug): bool;
}

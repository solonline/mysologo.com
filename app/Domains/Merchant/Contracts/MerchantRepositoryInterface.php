<?php

namespace App\Domains\Merchant\Contracts;

use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Models\Merchant;

interface MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant;

    public function isSlugTaken(string $slug): bool;
}

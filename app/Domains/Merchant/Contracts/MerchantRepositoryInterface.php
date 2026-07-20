<?php

namespace App\Domains\Merchant\Contracts;

use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Support\Collection;

interface MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant;

    public function isSlugTaken(string $slug): bool;

    /** @return Collection<int, Merchant> */
    public function listForCustomer(Customer $customer): Collection;

    public function findOwnedByCustomer(Customer $customer, int $merchantId): ?Merchant;
}

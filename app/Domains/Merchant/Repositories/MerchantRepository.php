<?php

namespace App\Domains\Merchant\Repositories;

use App\Domains\Merchant\Contracts\MerchantRepositoryInterface;
use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Support\Collection;

class MerchantRepository implements MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant
    {
        return Merchant::create([
            'customer_id' => $dto->customerId,
            'name' => $dto->name,
            'slug' => $dto->slug,
            'api_key' => $dto->hashedApiKey,
            'mode' => $dto->mode,
            'webhook_url' => $dto->webhookUrl,
            'allowed_currencies' => $dto->allowedCurrencies,
            'is_active' => true,
        ]);
    }

    public function isSlugTaken(string $slug): bool
    {
        return Merchant::where('slug', $slug)->exists();
    }

    public function listForCustomer(Customer $customer): Collection
    {
        return Merchant::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->get();
    }

    public function findOwnedByCustomer(Customer $customer, int $merchantId): ?Merchant
    {
        return Merchant::query()
            ->whereKey($merchantId)
            ->where('customer_id', $customer->id)
            ->first();
    }
}

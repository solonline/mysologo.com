<?php

namespace App\Repositories;

use App\Contracts\MerchantRepositoryInterface;
use App\DTOs\RegisterMerchantDTO;
use App\Models\Merchant;

class MerchantRepository implements MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant
    {
        return Merchant::create([
            'customer_id'        => $dto->customerId,
            'name'               => $dto->name,
            'slug'               => $dto->slug,
            'api_key'            => $dto->hashedApiKey,
            'mode'               => $dto->mode,
            'webhook_url'        => $dto->webhookUrl,
            'allowed_currencies' => $dto->allowedCurrencies,
            'is_active'          => true,
        ]);
    }

    public function isSlugTaken(string $slug): bool
    {
        return Merchant::where('slug', $slug)->exists();
    }
}

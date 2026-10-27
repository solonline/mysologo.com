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
            'webhook_secret' => $dto->webhookSecret,
            'mode' => $dto->mode,
            'webhook_url' => $dto->webhookUrl,
            'allowed_currencies' => $dto->allowedCurrencies,
            'is_active' => true,
        ]);
    }

    public function isSlugTaken(string $slug, ?int $exceptMerchantId = null): bool
    {
        $query = Merchant::where('slug', $slug);

        if ($exceptMerchantId !== null) {
            $query->where('id', '!=', $exceptMerchantId);
        }

        return $query->exists();
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

    public function customerHasTestMerchant(int $customerId): bool
    {
        return Merchant::query()
            ->where('customer_id', $customerId)
            ->where('mode', 'test')
            ->exists();
    }

    public function update(Merchant $merchant, array $data): Merchant
    {
        $merchant->update($data);

        return $merchant->fresh();
    }

    public function updateApiKey(Merchant $merchant, string $hashedApiKey): Merchant
    {
        $merchant->update(['api_key' => $hashedApiKey]);

        return $merchant->fresh();
    }

    public function updateWebhookSecret(Merchant $merchant, string $webhookSecret): Merchant
    {
        $merchant->update(['webhook_secret' => $webhookSecret]);

        return $merchant->fresh();
    }

    public function delete(Merchant $merchant): void
    {
        $merchant->delete();
    }
}

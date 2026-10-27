<?php

namespace App\Domains\Merchant\Contracts;

use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Support\Collection;

interface MerchantRepositoryInterface
{
    public function create(RegisterMerchantDTO $dto): Merchant;

    public function isSlugTaken(string $slug, ?int $exceptMerchantId = null): bool;

    /** @return Collection<int, Merchant> */
    public function listForCustomer(Customer $customer): Collection;

    public function findOwnedByCustomer(Customer $customer, int $merchantId): ?Merchant;

    public function customerHasTestMerchant(int $customerId): bool;

    /**
     * @param  array{name?: string, slug?: string, webhook_url?: string|null}  $data
     */
    public function update(Merchant $merchant, array $data): Merchant;

    public function delete(Merchant $merchant): void;

    public function updateApiKey(Merchant $merchant, string $hashedApiKey): Merchant;

    public function updateWebhookSecret(Merchant $merchant, string $webhookSecret): Merchant;
}

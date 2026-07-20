<?php

namespace App\Domains\Merchant\Contracts;

use App\Domains\Merchant\DTOs\CreateMerchantPaymentRequestDTO;
use App\Models\Customer;
use App\Models\MerchantPaymentRequest;
use Illuminate\Support\Collection;

interface MerchantPaymentRequestRepositoryInterface
{
    public function create(CreateMerchantPaymentRequestDTO $dto): MerchantPaymentRequest;

    public function findByStripePaymentIntentId(string $paymentIntentId): ?MerchantPaymentRequest;

    public function attachStripeIntent(MerchantPaymentRequest $paymentRequest, string $paymentIntentId): void;

    public function complete(MerchantPaymentRequest $paymentRequest, ?int $transactionId = null): void;

    public function fail(MerchantPaymentRequest $paymentRequest): void;

    /** @return Collection<int, MerchantPaymentRequest> */
    public function listForCustomer(Customer $customer, int $limit = 50): Collection;
}

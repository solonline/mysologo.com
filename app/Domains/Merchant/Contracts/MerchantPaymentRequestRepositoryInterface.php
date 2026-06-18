<?php

namespace App\Domains\Merchant\Contracts;

use App\Domains\Merchant\DTOs\CreateMerchantPaymentRequestDTO;
use App\Models\MerchantPaymentRequest;

interface MerchantPaymentRequestRepositoryInterface
{
    public function create(CreateMerchantPaymentRequestDTO $dto): MerchantPaymentRequest;

    public function findByStripePaymentIntentId(string $paymentIntentId): ?MerchantPaymentRequest;

    public function attachStripeIntent(MerchantPaymentRequest $paymentRequest, string $paymentIntentId): void;

    public function complete(MerchantPaymentRequest $paymentRequest, int $transactionId): void;

    public function fail(MerchantPaymentRequest $paymentRequest): void;
}

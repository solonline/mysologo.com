<?php

namespace App\Repositories;

use App\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\DTOs\CreateMerchantPaymentRequestDTO;
use App\Models\MerchantPaymentRequest;

class MerchantPaymentRequestRepository implements MerchantPaymentRequestRepositoryInterface
{
    public function create(CreateMerchantPaymentRequestDTO $dto): MerchantPaymentRequest
    {
        return MerchantPaymentRequest::create([
            'merchant_id'        => $dto->merchantId,
            'payer_id'           => $dto->payerId,
            'amount'             => $dto->amount,
            'currency'           => $dto->currency,
            'description'        => $dto->description,
            'external_reference' => $dto->externalReference,
            'callback_url'       => $dto->callbackUrl,
            'metadata'           => $dto->metadata,
            'items'              => $dto->items,
            'language'           => $dto->language ?? 'pt',
            'status'             => 'pending',
            'expires_at'         => $dto->expiresAt,
        ]);
    }

    public function findByStripePaymentIntentId(string $paymentIntentId): ?MerchantPaymentRequest
    {
        return MerchantPaymentRequest::where('stripe_payment_intent_id', $paymentIntentId)
            ->with('merchant')
            ->first();
    }

    public function attachStripeIntent(MerchantPaymentRequest $paymentRequest, string $paymentIntentId): void
    {
        $paymentRequest->update([
            'stripe_payment_intent_id' => $paymentIntentId,
            'status'                   => 'processing',
        ]);
    }

    public function complete(MerchantPaymentRequest $paymentRequest, int $transactionId): void
    {
        $paymentRequest->update([
            'status'         => 'completed',
            'transaction_id' => $transactionId,
            'paid_at'        => now(),
        ]);
    }

    public function fail(MerchantPaymentRequest $paymentRequest): void
    {
        $paymentRequest->update(['status' => 'failed']);
    }
}

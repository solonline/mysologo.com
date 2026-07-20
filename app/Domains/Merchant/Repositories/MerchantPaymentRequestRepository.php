<?php

namespace App\Domains\Merchant\Repositories;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\DTOs\CreateMerchantPaymentRequestDTO;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use Illuminate\Support\Collection;

class MerchantPaymentRequestRepository implements MerchantPaymentRequestRepositoryInterface
{
    public function create(CreateMerchantPaymentRequestDTO $dto): MerchantPaymentRequest
    {
        return MerchantPaymentRequest::create([
            'merchant_id' => $dto->merchantId,
            'payer_id' => $dto->payerId,
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'description' => $dto->description,
            'external_reference' => $dto->externalReference,
            'callback_url' => $dto->callbackUrl,
            'metadata' => $dto->metadata,
            'items' => $dto->items,
            'language' => $dto->language ?? 'pt',
            'payment_method_types' => $dto->paymentMethodTypes,
            'status' => 'pending',
            'expires_at' => $dto->expiresAt,
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
            'status' => 'processing',
        ]);
    }

    public function complete(MerchantPaymentRequest $paymentRequest, ?int $transactionId = null): void
    {
        $updates = [
            'status' => 'completed',
            'paid_at' => $paymentRequest->paid_at ?? now(),
        ];

        if ($transactionId !== null && $transactionId > 0) {
            $updates['transaction_id'] = $transactionId;
        }

        $paymentRequest->update($updates);
    }

    public function fail(MerchantPaymentRequest $paymentRequest): void
    {
        $paymentRequest->update(['status' => 'failed']);
    }

    public function listForCustomer(Customer $customer, int $limit = 50): Collection
    {
        $merchantIds = Merchant::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($merchantIds->isEmpty()) {
            return collect();
        }

        return MerchantPaymentRequest::query()
            ->whereIn('merchant_id', $merchantIds)
            ->with(['payer', 'stripeTransaction'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}

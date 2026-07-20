<?php

namespace App\Domains\Merchant\Services;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\Contracts\MerchantRepositoryInterface;
use App\Domains\Merchant\DTOs\CreateMerchantPaymentRequestDTO;
use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Services\StripePaymentIntentService;
use App\Domains\Wallet\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\Payer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MerchantService
{
    public function __construct(
        protected MerchantRepositoryInterface $merchantRepository,
        protected MerchantPaymentRequestRepositoryInterface $paymentRequestRepository,
        protected StripeGatewayInterface $stripeGateway,
        protected StripePaymentIntentService $paymentIntents,
    ) {}

    /** @return array{merchant: Merchant, api_key: string} */
    public function register(Customer $customer, array $data): array
    {
        $mode = $data['mode'] ?? 'test';
        $prefix = $mode === 'live' ? 'yoop_live_' : 'yoop_test_';
        $rawKey = $prefix . bin2hex(random_bytes(32));

        $dto = new RegisterMerchantDTO(
            customerId: $customer->id,
            name: $data['name'],
            slug: $this->uniqueSlug(Str::slug($data['name'])),
            hashedApiKey: hash('sha256', $rawKey),
            mode: $mode,
            webhookUrl: $data['webhook_url'] ?? null,
            allowedCurrencies: isset($data['allowed_currencies'])
                ? array_map('strtoupper', $data['allowed_currencies'])
                : null,
        );

        return [
            'merchant' => $this->merchantRepository->create($dto),
            'api_key' => $rawKey,
        ];
    }

    /** @return Collection<int, Merchant> */
    public function listForCustomer(Customer $customer): Collection
    {
        return $this->merchantRepository->listForCustomer($customer);
    }

    public function findOwnedByCustomer(Customer $customer, int $merchantId): ?Merchant
    {
        return $this->merchantRepository->findOwnedByCustomer($customer, $merchantId);
    }

    /** @return Collection<int, MerchantPaymentRequest> */
    public function listPaymentRequestsForCustomer(Customer $customer, int $limit = 50): Collection
    {
        return $this->paymentRequestRepository->listForCustomer($customer, $limit);
    }

    public function createPaymentRequest(Merchant $merchant, array $data): MerchantPaymentRequest
    {
        $currency = $merchant->customer->currency;

        $payer = Payer::findOrCreateFromData(
            fullName: $data['payer_name'] ?? null,
            emailAddress: $data['payer_email'] ?? null,
            phoneNumber: $data['payer_phone'] ?? null,
        );

        $dto = new CreateMerchantPaymentRequestDTO(
            merchantId: $merchant->id,
            payerId: $payer?->id,
            amount: $data['amount'],
            currency: $currency,
            description: $data['description'] ?? null,
            externalReference: $data['external_reference'] ?? null,
            callbackUrl: $data['callback_url'] ?? $merchant->webhook_url,
            metadata: $data['metadata'] ?? null,
            items: $data['items'] ?? null,
            expiresAt: isset($data['expires_in'])
                ? now()->addMinutes((int) $data['expires_in'])
                : now()->addYear(),
            language: $data['language'] ?? null,
            paymentMethodTypes: $data['payment_method_types'] ?? null,
        );

        return $this->paymentRequestRepository->create($dto);
    }

    /** @return array{client_secret: string}|null */
    public function initializePayment(MerchantPaymentRequest $paymentRequest): ?array
    {
        $paymentRequest->loadMissing('merchant.customer');
        $merchant = $paymentRequest->merchant;
        $customer = $merchant->customer;
        $mode = $merchant->mode;
        $currency = strtolower($customer->currency ?? 'eur');

        if ($paymentRequest->stripe_payment_intent_id) {
            $result = $this->stripeGateway->getPaymentIntentWithMode(
                $paymentRequest->stripe_payment_intent_id,
                $mode,
                $currency,
            );

            if ($result['status'] === 'success') {
                $intent = $result['data'];
                $intentStatus = $intent['status'] ?? null;

                $this->ensureLocalPaymentIntent($customer, $paymentRequest, $intent, $currency);

                if (! in_array($intentStatus, ['succeeded', 'canceled'], true)) {
                    return ['client_secret' => $intent['client_secret']];
                }

                if ($intentStatus === 'succeeded') {
                    if ($paymentRequest->status !== 'completed') {
                        $localPi = $this->paymentIntents->findByStripeId($intent['id']);
                        $transactionId = $localPi?->stripe_transaction_id
                            ?? $localPi?->stripeTransaction?->id;

                        $this->paymentRequestRepository->complete(
                            $paymentRequest,
                            $transactionId ? (int) $transactionId : null,
                        );
                    }
                    $paymentRequest->refresh();

                    return null;
                }

                $paymentRequest->update([
                    'stripe_payment_intent_id' => null,
                    'status' => 'pending',
                ]);
                $paymentRequest->refresh();
            }
        }

        $payload = [
            'amount' => $paymentRequest->amount,
            'currency' => $currency,
            'description' => $this->sanitizeDescription($paymentRequest->description),
            'metadata' => [
                'merchant_payment_request_id' => (string) $paymentRequest->id,
                'merchant_id' => (string) $paymentRequest->merchant_id,
                'customer_id' => (string) $customer->id,
                'purpose' => StripePaymentIntentPurpose::MerchantPayment->value,
            ],
        ];

        $paymentMethodTypes = $this->resolvePaymentMethodTypes($paymentRequest->payment_method_types);

        if ($paymentMethodTypes === []) {
            $payload['automatic_payment_methods'] = ['enabled' => true];
        } else {
            $payload['payment_method_types'] = $paymentMethodTypes;
        }

        $result = $this->stripeGateway->createPaymentIntentWithMode($payload, $mode);

        if ($result['status'] !== 'success') {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao inicializar pagamento.',
            );
        }

        $intent = $result['data'];

        $this->ensureLocalPaymentIntent($customer, $paymentRequest, $intent, $currency);

        $this->paymentRequestRepository->attachStripeIntent(
            $paymentRequest,
            $intent['id'],
        );

        return ['client_secret' => $intent['client_secret']];
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function ensureLocalPaymentIntent(
        Customer $customer,
        MerchantPaymentRequest $paymentRequest,
        array $intent,
        string $currency,
    ): void {
        $stripeId = (string) ($intent['id'] ?? '');

        if ($stripeId === '') {
            return;
        }

        if ($this->paymentIntents->findByStripeId($stripeId)) {
            return;
        }

        $this->paymentIntents->createFromStripeResponse(
            customer: $customer,
            intent: $intent,
            type: TransactionType::MERCHANT_PAYMENT,
            purpose: StripePaymentIntentPurpose::MerchantPayment,
            amount: (int) $paymentRequest->amount,
            currency: $currency,
            feeConfig: null,
            paymentMethodType: null,
            description: $paymentRequest->description ?? 'Merchant payment #'.$paymentRequest->id,
        );
    }

    /**
     * @param  list<string>|null  $types
     * @return list<string>
     */
    private function resolvePaymentMethodTypes(?array $types): array
    {
        if ($types === null || $types === []) {
            return [];
        }

        $blocked = ['bank_transfer', 'card_present'];

        return collect($types)
            ->filter(fn ($type) => is_string($type) && $type !== '')
            ->map(function (string $type): string {
                $type = strtolower(trim($type));

                if (str_starts_with($type, 'card_')) {
                    return 'card';
                }

                if (in_array($type, ['mb_way', 'mb-way', 'mb way'], true)) {
                    return 'mbway';
                }

                return $type;
            })
            ->reject(fn (string $type) => in_array($type, $blocked, true))
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeDescription(?string $description): ?string
    {
        return $description;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;

        while ($this->merchantRepository->isSlugTaken($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

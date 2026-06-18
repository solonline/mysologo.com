<?php

namespace App\Domains\Merchant\Services;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\Contracts\MerchantRepositoryInterface;
use App\Domains\Merchant\DTOs\CreateMerchantPaymentRequestDTO;
use App\Domains\Merchant\DTOs\RegisterMerchantDTO;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantPaymentRequest;
use App\Models\Payer;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use Illuminate\Support\Str;

class MerchantService
{
    public function __construct(
        protected MerchantRepositoryInterface $merchantRepository,
        protected MerchantPaymentRequestRepositoryInterface $paymentRequestRepository,
        protected StripeGatewayInterface $stripeGateway,
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
            expiresAt: now()->addMinutes($data['expires_in'] ?? 30),
            language: $data['language'] ?? null,
        );

        return $this->paymentRequestRepository->create($dto);
    }

    /** @return array{client_secret: string}|null */
    public function initializePayment(MerchantPaymentRequest $paymentRequest, array $paymentMethodTypes): ?array
    {
        $mode = $paymentRequest->merchant->mode;
        $currency = strtolower($paymentRequest->merchant->customer->currency ?? 'eur');

        if ($paymentRequest->stripe_payment_intent_id) {
            $result = $this->stripeGateway->getPaymentIntentWithMode(
                $paymentRequest->stripe_payment_intent_id,
                $mode,
                $currency,
            );

            if ($result['status'] === 'success') {
                $intentStatus = $result['data']['status'] ?? null;

                if (! in_array($intentStatus, ['succeeded', 'canceled'])) {
                    return ['client_secret' => $result['data']['client_secret']];
                }

                if ($intentStatus === 'succeeded') {
                    $paymentRequest->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                    ]);
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

        $result = $this->stripeGateway->createPaymentIntentWithMode([
            'amount' => $paymentRequest->amount,
            'currency' => $currency,
            'payment_method_types' => $paymentMethodTypes,
            'description' => $this->sanitizeDescription($paymentRequest->description),
            'metadata' => [
                'merchant_payment_request_id' => $paymentRequest->id,
                'merchant_id' => $paymentRequest->merchant_id,
            ],
        ], $mode);

        if ($result['status'] !== 'success') {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao inicializar pagamento.',
            );
        }

        $this->paymentRequestRepository->attachStripeIntent(
            $paymentRequest,
            $result['data']['id'],
        );

        return ['client_secret' => $result['data']['client_secret']];
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
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}

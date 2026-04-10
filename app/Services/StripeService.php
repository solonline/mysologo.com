<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\StripePaymentMethodFee;
use App\Models\Transaction;
use App\Providers\StripeProvider;
use App\Enums\TransactionType;
use App\Enums\TransactionDirection;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct(protected StripeProvider $stripeProvider)
    {
    }

    public function createCustomer(Customer $customer): array
    {
        $result = $this->stripeProvider->createCustomer([
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'metadata' => [
                'customer_id' => $customer->id,
                'customer_type' => $customer->type,
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao criar customer no Stripe', [
                'customer_id' => $customer->id,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_CUSTOMER_CREATION_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar customer no Stripe.'
            );
        }

        return $result['data'];
    }

    public function createPaymentIntent(
        Customer $customer,
        int $amount,
        int $paymentMethodFeeId,
        string $currency = 'eur',
        ?string $description = null,
    ): array {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_CUSTOMER_CREATION_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        $feeConfig = StripePaymentMethodFee::where('id', $paymentMethodFeeId)
            ->where('is_active', true)
            ->first();

        if (! $feeConfig) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Método de pagamento não suportado ou inativo.'
            );
        }

        $fees = $feeConfig->calculateFees($amount);

        $result = $this->stripeProvider->createPaymentIntent([
            'amount'               => $amount,
            'currency'             => $currency,
            'customer'             => $stripeAccount->stripe_customer_id,
            'payment_method_types' => [$feeConfig->payment_method],
            'description'          => $description,
            'metadata'             => [
                'customer_id'        => $customer->id,
                'payment_method_fee' => $feeConfig->payment_method,
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao criar payment intent no Stripe', [
                'customer_id' => $customer->id,
                'amount'      => $amount,
                'message'     => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar intenção de pagamento.'
            );
        }

        $intent = $result['data'];

        $transaction = Transaction::create([
            'customer_id'                  => $customer->id,
            'stripe_payment_method_fee_id' => $feeConfig->id,
            'type'                         => TransactionType::Stripe,
            'direction'                    => TransactionDirection::Credit,
            'stripe_payment_intent_id'     => $intent['id'],
            'payment_method_type'          => $feeConfig->payment_method,
            'amount'                       => $amount,
            'stripe_fee'                   => $fees['stripe_fee'],
            'platform_fee'                 => $fees['platform_fee'],
            'net_amount'                   => $fees['net_amount'],
            'currency'                     => strtoupper($currency),
            'status'                       => 'pending',
            'description'                  => $description,
            'stripe_metadata'              => $intent,
            'stripe_created_at'            => isset($intent['created']) ? now()->setTimestamp($intent['created']) : now(),
        ]);

        return [
            'transaction'   => $transaction,
            'client_secret' => $intent['client_secret'],
        ];
    }

    public function createSubscription(Customer $customer, string $stripeCustomerId, string $priceId, array $extra = []): array
    {
        $data = array_merge([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $priceId],
            ],
            'metadata' => [
                'customer_id' => $customer->id,
            ],
        ], $extra);

        $result = $this->stripeProvider->createSubscription($data);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao criar subscription no Stripe', [
                'customer_id' => $customer->id,
                'price_id' => $priceId,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_SUBSCRIPTION_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar assinatura.'
            );
        }

        return $result['data'];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $result = $this->stripeProvider->cancelSubscription($subscriptionId);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao cancelar subscription no Stripe', [
                'subscription_id' => $subscriptionId,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_SUBSCRIPTION_CANCEL_FAILED,
                502,
                $result['message'] ?? 'Erro ao cancelar assinatura.'
            );
        }

        return $result['data'];
    }

    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['type'] ?? null;
        $object    = $payload['data']['object'] ?? [];

        match ($eventType) {
            'payment_intent.succeeded'      => $this->handlePaymentIntentSucceeded($object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($object),
            'charge.updated'                => $this->handleChargeUpdated($object),
            default                         => Log::info('Stripe webhook ignorado', ['type' => $eventType]),
        };
    }

    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $transaction) {
            Log::warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status'          => 'succeeded',
            'stripe_metadata' => $intent,
        ]);

        Log::info('Stripe webhook: payment_intent.succeeded', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $transaction) {
            Log::warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status'          => 'failed',
            'stripe_metadata' => $intent,
        ]);

        Log::info('Stripe webhook: payment_intent.payment_failed', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);
    }

    private function handleChargeUpdated(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if (! $paymentIntentId) {
            return;
        }

        Transaction::where('stripe_payment_intent_id', $paymentIntentId)
            ->update(['stripe_charge_id' => $charge['id']]);
    }
}

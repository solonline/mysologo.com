<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Services;

use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\StripePaymentIntent;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Carbon\Carbon;

class StripePaymentIntentService
{
    public function createFromStripeResponse(
        Customer $customer,
        array $intent,
        TransactionType $type,
        StripePaymentIntentPurpose $purpose,
        int $amount,
        string $currency,
        ?StripePaymentMethodFee $feeConfig = null,
        ?string $paymentMethodType = null,
        ?string $description = null,
        ?int $tpaId = null,
        ?string $cashBalanceTransactionId = null,
    ): StripePaymentIntent {
        $status = StripePaymentIntentStatus::fromStripe($intent['status'] ?? null);

        return StripePaymentIntent::create([
            'customer_id' => $customer->id,
            'stripe_payment_intent_id' => $intent['id'],
            'stripe_payment_method_fee_id' => $feeConfig?->id,
            'tpa_id' => $tpaId,
            'type' => $type,
            'purpose' => $purpose,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'payment_method_type' => $paymentMethodType ?? $feeConfig?->payment_method,
            'status' => $status,
            'description' => $description,
            'stripe_metadata' => $intent,
            'stripe_cash_balance_transaction_id' => $cashBalanceTransactionId,
            'expires_at' => now()->addHours(24),
            'succeeded_at' => $status === StripePaymentIntentStatus::Succeeded ? now() : null,
            'stripe_created_at' => isset($intent['created'])
                ? Carbon::createFromTimestamp($intent['created'])
                : now(),
        ]);
    }

    public function findByStripeId(string $stripePaymentIntentId): ?StripePaymentIntent
    {
        return StripePaymentIntent::query()
            ->where('stripe_payment_intent_id', $stripePaymentIntentId)
            ->first();
    }

    public function findForCustomer(Customer $customer, int $id): ?StripePaymentIntent
    {
        return StripePaymentIntent::query()
            ->where('customer_id', $customer->id)
            ->with('stripeTransaction')
            ->find($id);
    }

    public function findByCashBalanceTransactionId(string $cashBalanceTransactionId): ?StripePaymentIntent
    {
        return StripePaymentIntent::query()
            ->where('stripe_cash_balance_transaction_id', $cashBalanceTransactionId)
            ->first();
    }

    public function syncStatusFromStripePayload(StripePaymentIntent $paymentIntent, array $intentPayload): StripePaymentIntent
    {
        $status = StripePaymentIntentStatus::fromStripe($intentPayload['status'] ?? null);
        $updates = [
            'status' => $status,
            'stripe_metadata' => $intentPayload,
        ];

        if ($status === StripePaymentIntentStatus::Succeeded && $paymentIntent->succeeded_at === null) {
            $updates['succeeded_at'] = now();
        }

        if ($status === StripePaymentIntentStatus::Canceled && $paymentIntent->canceled_at === null) {
            $updates['canceled_at'] = now();
        }

        $paymentIntent->update($updates);

        return $paymentIntent->fresh();
    }

    public function markCanceled(StripePaymentIntent $paymentIntent, ?array $intentPayload = null): StripePaymentIntent
    {
        $updates = [
            'status' => StripePaymentIntentStatus::Canceled,
            'canceled_at' => now(),
        ];

        if ($intentPayload !== null) {
            $updates['stripe_metadata'] = $intentPayload;
        }

        $paymentIntent->update($updates);

        return $paymentIntent->fresh();
    }

    public function markFailed(StripePaymentIntent $paymentIntent, ?array $intentPayload = null): StripePaymentIntent
    {
        $updates = [
            'status' => StripePaymentIntentStatus::Failed,
        ];

        if ($intentPayload !== null) {
            $updates['stripe_metadata'] = $intentPayload;
        }

        $paymentIntent->update($updates);

        return $paymentIntent->fresh();
    }

    public function linkTransaction(StripePaymentIntent $paymentIntent, StripeTransaction $transaction): StripePaymentIntent
    {
        if ($paymentIntent->stripe_transaction_id === $transaction->id) {
            return $paymentIntent;
        }

        $paymentIntent->update(['stripe_transaction_id' => $transaction->id]);

        return $paymentIntent->fresh();
    }

    public function resolveOrCreateTransaction(
        StripePaymentIntent $paymentIntent,
        string $transactionStatus = 'succeeded',
        ?array $intentPayload = null,
        ?array $extraTransactionAttributes = null,
    ): StripeTransaction {
        $existing = StripeTransaction::query()
            ->where('stripe_payment_intent_id', $paymentIntent->stripe_payment_intent_id)
            ->whereNull('related_transaction_id')
            ->first();

        if ($existing) {
            $this->linkTransaction($paymentIntent, $existing);

            return $existing;
        }

        $attributes = array_merge([
            'customer_id' => $paymentIntent->customer_id,
            'stripe_payment_method_fee_id' => $paymentIntent->stripe_payment_method_fee_id,
            'tpa_id' => $paymentIntent->tpa_id,
            'type' => $paymentIntent->resolveTransactionType(),
            'direction' => TransactionDirection::Credit,
            'stripe_payment_intent_id' => $paymentIntent->stripe_payment_intent_id,
            'stripe_cash_balance_transaction_id' => $paymentIntent->stripe_cash_balance_transaction_id,
            'payment_method_type' => $paymentIntent->payment_method_type,
            'amount' => $paymentIntent->amount,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => $paymentIntent->currency,
            'status' => $transactionStatus,
            'description' => $paymentIntent->description,
            'stripe_metadata' => $intentPayload ?? $paymentIntent->stripe_metadata,
            'stripe_created_at' => $paymentIntent->stripe_created_at ?? now(),
        ], $extraTransactionAttributes ?? []);

        $transaction = StripeTransaction::create($attributes);

        $this->linkTransaction($paymentIntent, $transaction);

        return $transaction;
    }
}

<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
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

        if (!$stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        $feeConfig = StripePaymentMethodFee::where('id', $paymentMethodFeeId)
            ->where('is_active', true)
            ->first();

        if (!$feeConfig) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Método de pagamento não suportado ou inativo.'
            );
        }

        if (strtolower($currency) !== strtolower($feeConfig->currency)) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Moeda incompatível com a configuração do método de pagamento.'
            );
        }

        $currency = strtolower($currency);
        $fees = $feeConfig->calculateFees($amount);

        $result = $this->stripeProvider->createPaymentIntent([
            'amount' => $amount,
            'currency' => $currency,
            'customer' => $stripeAccount->stripe_customer_id,
            'payment_method_types' => [$feeConfig->payment_method],
            'description' => $description,
            'metadata' => [
                'customer_id' => $customer->id,
                'payment_method_fee' => $feeConfig->payment_method,
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao criar payment intent no Stripe', [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $feeConfig->payment_method,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar intenção de pagamento.'
            );
        }

        $intent = $result['data'];

        try {
            $transaction = Transaction::create([
                'customer_id' => $customer->id,
                'stripe_payment_method_fee_id' => $feeConfig->id,
                'type' => TransactionType::Stripe,
                'direction' => TransactionDirection::Credit,
                'stripe_payment_intent_id' => $intent['id'],
                'payment_method_type' => $feeConfig->payment_method,
                'amount' => $amount,
                'stripe_fee' => $fees['stripe_fee'],
                'platform_fee' => $fees['platform_fee'],
                'net_amount' => $fees['net_amount'],
                'currency' => strtoupper($currency),
                'status' => 'pending',
                'description' => $description,
                'stripe_metadata' => $intent,
                'stripe_created_at' => isset($intent['created'])
                    ? \Carbon\Carbon::createFromTimestamp($intent['created'])
                    : now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao registrar transaction após criar payment intent', [
                'customer_id' => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a transação. Payment intent criado no Stripe: ' . $intent['id']
            );
        }

        $this->incrementBalance($customer->id, strtoupper($currency), pending: $fees['net_amount']);

        return [
            'transaction' => $transaction,
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
        $object = $payload['data']['object'] ?? [];

        match ($eventType) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($object),
            'charge.updated' => $this->handleChargeUpdated($object),
            default => Log::info('Stripe webhook ignorado', ['type' => $eventType]),
        };
    }

    public function getCashBalance(string $customerId): array
    {
        $result = $this->stripeProvider->getCashBalance($customerId);

        if ($result['status'] !== 'success') {
            Log::warning('Falha ao buscar cash balance no Stripe', [
                'customer_id' => $customerId,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_CASH_BALANCE_FAILED,
                502,
                $result['message'] ?? 'Erro ao buscar saldo no Stripe.'
            );
        }

        return $result['data'];
    }

    public function getFundingInstructions(Customer $customer): array
    {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_CUSTOMER_CREATION_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        return [
            'funding_type'                => $stripeAccount->funding_type,
            'bank_transfer_type'          => $stripeAccount->bank_transfer_type,
            'funding_iban'                => $stripeAccount->funding_iban,
            'funding_bic'                 => $stripeAccount->funding_bic,
            'funding_account_holder_name' => $stripeAccount->funding_account_holder_name,
            'funding_country'             => $stripeAccount->funding_country,
            'funding_financial_addresses' => $stripeAccount->funding_financial_addresses,
        ];
    }

    public function createVban(Customer $customer): array
    {
        $stripeAccount = $customer->stripeAccount;

        if (!$stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_CUSTOMER_CREATION_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        $currency = strtolower($customer->currency ?? 'eur');

        // Apenas USD, EUR e GBP são suportados pelo Stripe bank_transfer.
        // Qualquer outro valor recai no padrão EUR.
        $transferMap = [
            'usd' => ['type' => 'us_bank_transfer', 'extra' => []],
            'gbp' => ['type' => 'gb_bank_transfer', 'extra' => []],
            'eur' => ['type' => 'eu_bank_transfer', 'extra' => ['eu_bank_transfer' => ['country' => 'DE']]],
        ];

        $transfer = $transferMap[$currency] ?? $transferMap['eur'];
        $currency  = array_key_exists($currency, $transferMap) ? $currency : 'eur';

        $data = [
            'funding_type' => 'bank_transfer',
            'bank_transfer' => array_merge(['type' => $transfer['type']], $transfer['extra']),
            'currency'     => $currency,
        ];

        $result = $this->stripeProvider->createCustomerFundingInstructions($stripeAccount->stripe_customer_id, $data);

        \Log::info('Resultado do createCustomerFundingInstructions', ['result' => $result]);
        if ($result['status'] !== 'success') {
            Log::warning('Falha ao criar funding instructions no Stripe', [
                'customer_id' => $customer->id,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_FUNDING_INSTRUCTIONS_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar funding instructions.'
            );
        }

        $funding = $result['data'];
        $bankTransfer = $funding['bank_transfer'] ?? [];
        $financialAddresses = $bankTransfer['financial_addresses'] ?? [];
        $addresses = collect($financialAddresses);

        // Tenta extrair campos canónicos por prioridade: iban > swift > aba
        $ibanAddr  = $addresses->firstWhere('type', 'iban');
        $swiftAddr = $addresses->firstWhere('type', 'swift');
        $abaAddr   = $addresses->firstWhere('type', 'aba');

        if ($ibanAddr) {
            $detail               = $ibanAddr['iban'];
            $fundingIban          = $detail['iban'] ?? null;
            $fundingBic           = $detail['bic'] ?? null;
            $fundingAccountHolder = $detail['account_holder_name'] ?? null;
            $fundingCountry       = $detail['country'] ?? $bankTransfer['country'] ?? null;
        } elseif ($swiftAddr) {
            $detail               = $swiftAddr['swift'];
            $fundingIban          = $detail['account_number'] ?? null; // número da conta
            $fundingBic           = $detail['swift_code'] ?? null;
            $fundingAccountHolder = $detail['account_holder_name'] ?? null;
            $fundingCountry       = $detail['account_holder_address']['country'] ?? $bankTransfer['country'] ?? null;
        } elseif ($abaAddr) {
            $detail               = $abaAddr['aba'];
            $fundingIban          = $detail['account_number'] ?? null;
            $fundingBic           = $detail['routing_number'] ?? null;
            $fundingAccountHolder = $detail['account_holder_name'] ?? null;
            $fundingCountry       = $detail['account_holder_address']['country'] ?? $bankTransfer['country'] ?? null;
        } else {
            $fundingIban = $fundingBic = $fundingAccountHolder = $fundingCountry = null;
        }

        $stripeAccount->update([
            'funding_type'                => $funding['funding_type'] ?? null,
            'bank_transfer_type'          => $bankTransfer['type'] ?? null,
            'funding_iban'                => $fundingIban,
            'funding_bic'                 => $fundingBic,
            'funding_account_holder_name' => $fundingAccountHolder,
            'funding_country'             => $fundingCountry,
            'funding_financial_addresses' => $financialAddresses,
        ]);

        return $funding;
    }

    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (!$transaction) {
            Log::warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status' => 'succeeded',
            'stripe_metadata' => $intent,
        ]);

        // pending → available para o customer
        $this->incrementBalance(
            $transaction->customer_id,
            $transaction->currency,
            available: $transaction->net_amount,
            pending: -$transaction->net_amount,
        );

        $this->createPlatformTransfer($transaction);

        Log::info('Stripe webhook: payment_intent.succeeded', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);
    }

    private function  createPlatformTransfer(Transaction $sourceTransaction): void
    {
        $platformCustomer = Customer::where('type', 'platform')->first();

        if (!$platformCustomer) {
            Log::warning('Stripe webhook: conta platform não encontrada para transfer', [
                'transaction_id' => $sourceTransaction->id,
            ]);
            return;
        }

        try {
            Transaction::create([
                'customer_id'                  => $platformCustomer->id,
                'stripe_payment_method_fee_id' => $sourceTransaction->stripe_payment_method_fee_id,
                'related_transaction_id'       => $sourceTransaction->id,
                'type'                         => TransactionType::P2P,
                'direction'                    => TransactionDirection::Credit,
                'stripe_payment_intent_id'     => $sourceTransaction->stripe_payment_intent_id,
                'stripe_charge_id'             => $sourceTransaction->stripe_charge_id,
                'payment_method_type'          => $sourceTransaction->payment_method_type,
                'amount'                       => $sourceTransaction->net_amount,
                'stripe_fee'                   => 0,
                'platform_fee'                 => 0,
                'net_amount'                   => $sourceTransaction->net_amount,
                'currency'                     => $sourceTransaction->currency,
                'status'                       => 'succeeded',
                'description'                  => "Platform transfer from transaction #{$sourceTransaction->id}",
                'stripe_created_at'            => now(),
            ]);

            $this->incrementBalance(
                $platformCustomer->id,
                $sourceTransaction->currency,
                available: $sourceTransaction->net_amount,
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao criar platform transfer transaction', [
                'source_transaction_id' => $sourceTransaction->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function incrementBalance(int $customerId, string $currency, int $available = 0, int $pending = 0): void
    {
        $balance = CustomerBalance::firstOrCreate(
            ['customer_id' => $customerId, 'currency' => strtoupper($currency)],
            ['available' => 0, 'pending' => 0],
        );

        $balance->increment('available', $available);
        $balance->increment('pending', $pending);
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (!$transaction) {
            Log::warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status' => 'failed',
            'stripe_metadata' => $intent,
        ]);

        // reverte o pending do customer
        $this->incrementBalance(
            $transaction->customer_id,
            $transaction->currency,
            pending: -$transaction->net_amount,
        );

        Log::info('Stripe webhook: payment_intent.payment_failed', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);
    }

    private function handleChargeUpdated(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if (!$paymentIntentId) {
            return;
        }

        Transaction::where('stripe_payment_intent_id', $paymentIntentId)
            ->update(['stripe_charge_id' => $charge['id']]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerStripeAccount;
use App\Models\StripePaymentMethodFee;
use App\Models\Transaction;
use App\Providers\StripeProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeService
{
    private const PAYMENT_METHOD_CURRENCIES = [
        'mbway'  => ['eur'],
        'twint'  => ['chf'],
        'blik'   => ['pln'],
        'bancontact' => ['eur'],
        'ideal'  => ['eur'],
        'sofort' => ['eur'],
        'eps'    => ['eur'],
        'giropay' => ['eur'],
        'p24'    => ['eur', 'pln'],
    ];

    public function __construct(protected StripeProvider $stripeProvider)
    {
    }

    public function validatePaymentMethodCurrency(string $paymentMethod, string $currency): void
    {
        $method = strtolower($paymentMethod);
        $cur    = strtolower($currency);

        if (!isset(self::PAYMENT_METHOD_CURRENCIES[$method])) {
            return;
        }

        $allowed = self::PAYMENT_METHOD_CURRENCIES[$method];

        if (!in_array($cur, $allowed, strict: true)) {
            $allowedList = implode(', ', array_map(strtoupper(...), $allowed));
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                "O método de pagamento '{$paymentMethod}' apenas aceita as moedas: {$allowedList}."
            );
        }
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
            Log::channel('stripe')->warning('stripe_service: createCustomer', [
                'customer_id' => $customer->id,
                'message'     => $result['message'],
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

        $this->validatePaymentMethodCurrency($feeConfig->payment_method, $currency);

        if (strtolower($currency) !== strtolower($feeConfig->currency)) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Moeda incompatível com a configuração do método de pagamento.'
            );
        }

        $currency = strtolower($currency);

        $paymentMethodTypes = StripePaymentMethodFee::where('is_active', true)
            ->where('currency', strtoupper($currency))
            ->orderBy('id')
            ->get()
            ->map(fn ($f) => $f->stripePaymentMethodType())
            ->unique()
            ->values()
            ->toArray();

        $result = $this->stripeProvider->createPaymentIntent([
            'amount'               => $amount,
            'currency'             => $currency,
            'customer'             => $stripeAccount->stripe_customer_id,
            'description'          => $description,
            'payment_method_types' => $paymentMethodTypes,
            'metadata'             => [
                'customer_id'        => $customer->id,
                'payment_method_fee' => $feeConfig->payment_method,
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: createPaymentIntent', [
                'customer_id'    => $customer->id,
                'amount'         => $amount,
                'currency'       => $currency,
                'payment_method' => $feeConfig->payment_method,
                'message'        => $result['message'],
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
                'type' => TransactionType::from($feeConfig->payment_method),
                'direction' => TransactionDirection::Credit,
                'stripe_payment_intent_id' => $intent['id'],
                'payment_method_type' => $feeConfig->payment_method,
                'amount' => $amount,
                'stripe_fee' => 0,
                'platform_fee' => 0,
                'net_amount' => 0,
                'currency' => strtoupper($currency),
                'status' => 'pending',
                'description' => $description,
                'stripe_metadata' => $intent,
                'stripe_created_at' => isset($intent['created'])
                    ? Carbon::createFromTimestamp($intent['created'])
                    : now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('stripe_service: transaction_create_failed', [
                'customer_id'              => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception'                => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a transação. Payment intent criado no Stripe: ' . $intent['id']
            );
        }

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
            Log::channel('stripe')->warning('stripe_service: createSubscription', [
                'customer_id' => $customer->id,
                'price_id'    => $priceId,
                'message'     => $result['message'],
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
            Log::channel('stripe')->warning('stripe_service: cancelSubscription', [
                'subscription_id' => $subscriptionId,
                'message'         => $result['message'],
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
            'payment_intent.succeeded'                          => $this->handlePaymentIntentSucceeded($object),
            'payment_intent.payment_failed'                     => $this->handlePaymentIntentFailed($object),
            'charge.updated'                                    => $this->handleChargeUpdated($object),
            'customer_cash_balance_transaction.created'         => $this->handleCashBalanceFunded($object),
            default => Log::info('Stripe webhook ignorado', ['type' => $eventType]),
        };
    }

    public function getCashBalance(string $customerId): array
    {
        $result = $this->stripeProvider->getCashBalance($customerId);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: getCashBalance', [
                'customer_id' => $customerId,
                'message'     => $result['message'],
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
                ErrorCode::STRIPE_FUNDING_INSTRUCTIONS_FAILED,
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

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: createVban', [
                'customer_id' => $customer->id,
                'message'     => $result['message'],
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

    private function handleCashBalanceFunded(array $object): void
    {
        $stripeCustomerId = $object['customer'] ?? null;
        $currency         = strtoupper($object['currency'] ?? '');
        $netAmount        = $object['net_amount'] ?? 0;

        if (! $stripeCustomerId || ! $currency || $netAmount <= 0) {
            Log::channel('stripe')->warning('Stripe webhook: cash_balance.funded com dados inválidos', $object);
            return;
        }

        $stripeAccount = CustomerStripeAccount::where('stripe_customer_id', $stripeCustomerId)->first();

        if (! $stripeAccount) {
            Log::channel('stripe')->warning('Stripe webhook: CustomerStripeAccount não encontrada', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);
            return;
        }

        $transactionId = $object['id'] ?? null;

        if ($transactionId && Transaction::where('stripe_cash_balance_transaction_id', $transactionId)->exists()) {
            Log::channel('stripe')->info('Stripe webhook: cash_balance.funded já processado', [
                'stripe_cash_balance_transaction_id' => $transactionId,
            ]);
            return;
        }

        try {
            Transaction::create([
                'customer_id'                           => $stripeAccount->customer_id,
                'type'                                  => TransactionType::BANK_TRANSFER,
                'direction'                             => TransactionDirection::Credit,
                'payment_method_type'                   => 'bank_transfer',
                'amount'                                => $netAmount,
                'stripe_fee'                            => 0,
                'platform_fee'                          => 0,
                'net_amount'                            => $netAmount,
                'currency'                              => $currency,
                'status'                                => 'succeeded',
                'description'                           => 'Cash balance funded via bank transfer',
                'stripe_cash_balance_transaction_id'    => $object['id'] ?? null,
                'bank_transfer_type'                    => $object['funded']['bank_transfer']['type'] ?? null,
                'bank_transfer_reference'               => $object['funded']['bank_transfer']['reference'] ?? null,
                'bank_transfer_sender_name'             => $object['funded']['bank_transfer']['eu_bank_transfer']['sender_name'] ?? null,
                'bank_transfer_bic'                     => $object['funded']['bank_transfer']['eu_bank_transfer']['bic'] ?? null,
                'bank_transfer_iban_last4'              => $object['funded']['bank_transfer']['eu_bank_transfer']['iban_last4'] ?? null,
                'bank_transfer_amount'                  => $netAmount,
                'stripe_metadata'                       => $object,
                'stripe_created_at'                     => isset($object['created'])
                    ? Carbon::createFromTimestamp($object['created'])
                    : now(),
            ]);

            $this->incrementBalance($stripeAccount->customer_id, $currency, available: $netAmount);
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('Stripe webhook: erro ao processar cash_balance.funded', [
                'stripe_customer_id' => $stripeCustomerId,
                'exception'          => $e->getMessage(),
            ]);
            return;
        }

        Log::channel('stripe')->info('Stripe webhook: customer.cash_balance.funded', [
            'stripe_customer_id' => $stripeCustomerId,
            'currency'           => $currency,
            'net_amount'         => $netAmount,
        ]);
    }

    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (!$transaction) {
            Log::channel('stripe')->warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status' => 'succeeded',
            'stripe_metadata' => $intent,
        ]);

        if ($transaction->net_amount > 0) {
            // charge.updated já correu: move pending → available
            $this->incrementBalance(
                $transaction->customer_id,
                $transaction->currency,
                available: $transaction->net_amount,
                pending:  -$transaction->net_amount,
            );
            $this->createPlatformTransfer($transaction);
        }
        // Se net_amount=0, charge.updated ainda não chegou — ele creditará directamente em available

        Log::info('Stripe webhook: payment_intent.succeeded', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);
    }

    private function  createPlatformTransfer(Transaction $sourceTransaction): void
    {
        $platformCustomer = Cache::rememberForever('platform_customer', fn () => Customer::where('type', 'platform')->first());

        if (!$platformCustomer) {
            Log::channel('stripe')->warning('Stripe webhook: conta platform não encontrada para transfer', [
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
            Log::channel('stripe')->error('Falha ao criar platform transfer transaction', [
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

        CustomerBalance::where('id', $balance->id)->update([
            'available' => DB::raw("available + {$available}"),
            'pending'   => DB::raw("pending + {$pending}"),
        ]);
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (!$transaction) {
            Log::channel('stripe')->warning('Stripe webhook: transaction não encontrada', ['payment_intent_id' => $intent['id']]);
            return;
        }

        $transaction->update([
            'status' => 'failed',
            'stripe_metadata' => $intent,
        ]);

        // Reverte o pending adicionado pelo charge.updated (se tiver chegado antes do failed)
        if ($transaction->net_amount > 0) {
            $this->incrementBalance(
                $transaction->customer_id,
                $transaction->currency,
                pending: -$transaction->net_amount,
            );
        }

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

        $balanceTxnId = $charge['balance_transaction'] ?? null;

        if (!\is_string($balanceTxnId) || !str_starts_with($balanceTxnId, 'txn_')) {
            return;
        }

        $result = $this->stripeProvider->getBalanceTransaction($balanceTxnId);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: getBalanceTransaction failed', [
                'balance_transaction_id' => $balanceTxnId,
                'payment_intent_id'      => $paymentIntentId,
            ]);
            return;
        }

        $realStripeFee = $result['data']['fee'] ?? null;

        if ($realStripeFee === null) {
            return;
        }

        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntentId)
            ->whereNull('related_transaction_id')
            ->first();

        if (!$transaction) {
            return;
        }

        // Classifica o método de pagamento real (para cartões, pelo país do cartão)
        $pmDetails  = $charge['payment_method_details'] ?? [];
        $pmType     = $pmDetails['type'] ?? null;
        $feeMethod  = $this->resolvePaymentMethodCategory($pmType, $pmDetails);

        $feeConfig = StripePaymentMethodFee::where('payment_method', $feeMethod)
            ->where('currency', $transaction->currency)
            ->where('is_active', true)
            ->first();

        $platformFee   = $feeConfig ? $feeConfig->calculatePlatformFee($transaction->amount) : 0;
        $realNetAmount = $transaction->amount - $realStripeFee - $platformFee;

        $transaction->update([
            'stripe_payment_method_fee_id' => $feeConfig?->id,
            'payment_method_type'          => $feeMethod,
            'stripe_fee'                   => $realStripeFee,
            'platform_fee'                 => $platformFee,
            'net_amount'                   => $realNetAmount,
        ]);

        if ($transaction->status === 'succeeded') {
            // Fora de ordem: succeeded usou net_amount=0, credita agora directamente em available
            $this->incrementBalance($transaction->customer_id, $transaction->currency, available: $realNetAmount);
            $this->createPlatformTransfer($transaction->fresh());
        } else {
            // Fluxo normal: adiciona ao pending; succeeded moverá para available
            $this->incrementBalance($transaction->customer_id, $transaction->currency, pending: $realNetAmount);
        }

        Log::channel('stripe')->info('stripe_service: charge.updated — fees e categoria aplicados', [
            'payment_intent_id'      => $paymentIntentId,
            'balance_transaction_id' => $balanceTxnId,
            'payment_method_category'=> $feeMethod,
            'stripe_fee'             => $realStripeFee,
            'platform_fee'           => $platformFee,
            'net_amount'             => $realNetAmount,
        ]);
    }

    private function resolvePaymentMethodCategory(string $pmType, array $pmDetails): string
    {
        if ($pmType !== 'card') {
            return $pmType;
        }

        $country = strtoupper($pmDetails['card']['country'] ?? '');

        $eeaCountries = [
            'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
            'FR','GR','HR','HU','IE','IS','IT','LI','LT','LU',
            'LV','MT','NL','NO','PL','PT','RO','SE','SI','SK',
        ];

        if (\in_array($country, $eeaCountries, true)) {
            return 'card';
        }

        if ($country === 'GB') {
            return 'card_non_eea';
        }

        // Tenta corresponder a qualquer config card_* activa para este país
        // (ex: card_non_eea cobre GB e outros fora da EEA)
        return 'card_international';
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Services;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\Jobs\DispatchMerchantCallback;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\StripePaymentWalletService;
use App\Domains\Wallet\Services\TopupWallesterFundingPolicy;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerStripeAccount;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeService
{
    private const PAYMENT_METHOD_CURRENCIES = [
        'mbway' => ['eur'],
        'twint' => ['chf'],
        'blik' => ['pln'],
        'bancontact' => ['eur'],
        'ideal' => ['eur'],
        'sofort' => ['eur'],
        'eps' => ['eur'],
        'giropay' => ['eur'],
        'p24' => ['eur', 'pln'],
    ];

    public function __construct(
        protected StripeGatewayInterface $stripeGateway,
        protected MerchantPaymentRequestRepositoryInterface $merchantPaymentRequestRepository,
        protected IntegrationAccountService $integrationAccounts,
        protected StripePaymentWalletService $stripePaymentWallet,
        protected TopupWallesterFundingPolicy $topupFundingPolicy,
    ) {}

    public function validatePaymentMethodCurrency(string $paymentMethod, string $currency): void
    {
        $method = strtolower($paymentMethod);
        $cur = strtolower($currency);

        if (! isset(self::PAYMENT_METHOD_CURRENCIES[$method])) {
            return;
        }

        $allowed = self::PAYMENT_METHOD_CURRENCIES[$method];

        if (! in_array($cur, $allowed, strict: true)) {
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
        $result = $this->stripeGateway->createCustomer([
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

    public function createCustomerFundingInstructions(Customer $customer): array
    {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_FUNDING_INSTRUCTIONS_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        $currency = strtolower((string) ($customer->currency ?? $stripeAccount->currency ?? 'eur'));
        $fundingType = match ($currency) {
            'eur' => 'eu_bank_transfer',
            'gbp' => 'gb_bank_transfer',
            'usd' => 'usd_bank_transfer',
            default => 'eu_bank_transfer',
        };

        $data = [
            'bank_transfer[type]' => $fundingType,
            'currency' => $currency,
            'funding_type' => 'bank_transfer',
        ];

        $result = $this->stripeGateway->createCustomerFundingInstructions(
            $stripeAccount->stripe_customer_id,
            $data,
        );

        if (($result['status'] ?? null) !== 'success') {
            $message = $result['message'] ?? 'Erro ao criar instruções de funding no Stripe.';

            $this->integrationAccounts->markStripeFundingFailed($stripeAccount->fresh(), $message);

            throw new ApiException(
                ErrorCode::STRIPE_FUNDING_INSTRUCTIONS_FAILED,
                502,
                $message,
            );
        }

        $fundingInstructions = $result['data'] ?? [];

        try {
            $this->integrationAccounts->handleStripeFundingInstructionsCreated(
                $stripeAccount->fresh(),
                $fundingInstructions,
            );
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('Failed to process create viban funding instructions', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            $this->integrationAccounts->markStripeFundingFailed(
                $stripeAccount->fresh(),
                $e->getMessage(),
            );

            throw new ApiException(
                ErrorCode::STRIPE_FUNDING_INSTRUCTIONS_FAILED,
                502,
                'Erro ao persistir instruções de funding.'
            );
        }

        return $fundingInstructions;
    }

    /** @return array<string, mixed>|null */
    public function getFundingInstructions(Customer $customer): ?array
    {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            return null;
        }

        return [
            'stripe_customer_id' => $stripeAccount->stripe_customer_id,
            'currency' => $stripeAccount->currency,
            'status' => $stripeAccount->status,
            'funding_status' => $stripeAccount->funding_status,
            'funding_status_reason' => $stripeAccount->funding_status_reason,
            'viban' => $stripeAccount->viban,
            'funding_type' => $stripeAccount->funding_type,
            'bank_transfer_type' => $stripeAccount->bank_transfer_type,
            'funding_iban' => $stripeAccount->funding_iban,
            'funding_bic' => $stripeAccount->funding_bic,
            'funding_account_holder_name' => $stripeAccount->funding_account_holder_name,
            'funding_country' => $stripeAccount->funding_country,
            'funding_financial_addresses' => $stripeAccount->funding_financial_addresses,
        ];
    }

    public function createPaymentIntent(
        Customer $customer,
        int $amount,
        int $paymentMethodFeeId,
        string $currency = 'eur',
        ?string $description = null,
        bool $isTopup = false,
    ): array {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
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
            ->reject(fn ($type) => $type === 'bank_transfer') // card_present é só para pagamentos terminal, não deve aparecer como opção na criação de intents normais
            ->toArray();

        $result = $this->stripeGateway->createPaymentIntent([
            'amount' => $amount,
            'currency' => $currency,
            'customer' => $stripeAccount->stripe_customer_id,
            'description' => $description,
            'payment_method_types' => $paymentMethodTypes,
            'metadata' => array_filter([
                'customer_id' => $customer->id,
                'payment_method_fee' => $feeConfig->payment_method,
                'purpose' => $isTopup ? 'topup' : null,
            ]),
        ]);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: createPaymentIntent', [
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
            $transaction = StripeTransaction::create([
                'customer_id' => $customer->id,
                'stripe_payment_method_fee_id' => $feeConfig->id,
                'type' => $isTopup ? TransactionType::TOPUP : TransactionType::from($feeConfig->payment_method),
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

            if ($isTopup) {
            }
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('stripe_service: transaction_create_failed', [
                'customer_id' => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a transação. Payment intent criado no Stripe: '.$intent['id']
            );
        }

        return [
            'transaction' => $transaction,
            'client_secret' => $intent['client_secret'],
        ];
    }

    public function createTerminalConnectionToken(string $currency = 'eur'): string
    {
        $result = $this->stripeGateway->createTerminalConnectionToken($currency);

        if ($result['status'] !== 'success') {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar connection token.'
            );
        }

        return $result['data']['secret'];
    }

    public function createTerminalPaymentIntent(
        Customer $customer,
        int $amount,
        string $currency = 'eur',
        ?string $description = null,
        ?string $tpaSerialNumber = null
    ): array {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Cliente não possui conta Stripe associada.'
            );
        }

        if ($customer->tpas->contains(function ($tpa) use ($tpaSerialNumber) {
            return $tpa->serial_number === $tpaSerialNumber;
        })) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'The provided TPA serial number does not match the customer\'s registered TPA.'
            );
        }

        $tpa = $customer->tpas()->where('serial', $tpaSerialNumber)->first();

        \Log::info('Creating terminal payment intent', [
            'customer_id' => $customer->id,
            'amount' => $amount,
            'currency' => $currency,
            'tpa_serial_number' => $tpaSerialNumber,
        ]);
        $currency = strtolower($currency);

        $result = $this->stripeGateway->createPaymentIntent([
            'amount' => $amount,
            'currency' => $currency,
            'customer' => $stripeAccount->stripe_customer_id,
            'description' => $description,
            'payment_method_types' => ['card_present'],
            'capture_method' => 'automatic',
            'metadata' => [
                'customer_id' => $customer->id,
                'payment_method_fee' => 'card_present',
            ],
        ]);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: createTerminalPaymentIntent', [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao criar intenção de pagamento terminal.'
            );
        }

        $intent = $result['data'];

        try {
            $transaction = StripeTransaction::create([
                'tpa_id' => $tpa->id,
                'customer_id' => $customer->id,
                'type' => TransactionType::CARD_PRESENT,
                'direction' => TransactionDirection::Credit,
                'stripe_payment_intent_id' => $intent['id'],
                'payment_method_type' => 'card_present',
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
            Log::channel('stripe')->error('stripe_service: terminal_transaction_create_failed', [
                'customer_id' => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a transação. Payment intent criado no Stripe: '.$intent['id']
            );
        }

        return [
            'transaction' => $transaction,
            'client_secret' => $intent['client_secret'],
        ];
    }

    public function captureTerminalPayment(StripeTransaction $transaction): array
    {
        if ($transaction->payment_method_type !== 'card_present') {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Esta transação não é um pagamento terminal.'
            );
        }

        if (! \in_array($transaction->status, ['pending', 'requires_capture'])) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Transação não está num estado capturável.'
            );
        }

        $result = $this->stripeGateway->capturePaymentIntent($transaction->stripe_payment_intent_id);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: captureTerminalPayment', [
                'transaction_id' => $transaction->id,
                'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao capturar pagamento terminal.'
            );
        }

        Log::channel('stripe')->info('stripe_service: captureTerminalPayment — captura solicitada', [
            'transaction_id' => $transaction->id,
            'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
        ]);

        return $result['data'];
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

        $result = $this->stripeGateway->createSubscription($data);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: createSubscription', [
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
        $result = $this->stripeGateway->cancelSubscription($subscriptionId);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: cancelSubscription', [
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
            'customer.cash_balance_settings.updated' => $this->integrationAccounts->handleStripeWebhook($eventType, $object),
            'customer.cash_balance_transaction.created' => \Log::info('Stripe webhook ignorado', ['type' => $eventType]),
            default => Log::info('Stripe webhook ignorado', ['type' => $eventType]),
        };
    }

    public function getCashBalance(string $customerId): array
    {
        $result = $this->stripeGateway->getCashBalance($customerId);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: getCashBalance', [
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

    private function handleCashBalanceFunded(array $object): void
    {
        $stripeCustomerId = $object['customer'] ?? null;
        $currency = strtoupper($object['currency'] ?? '');
        $netAmount = $object['net_amount'] ?? 0;

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

        if ($transactionId && StripeTransaction::where('stripe_cash_balance_transaction_id', $transactionId)->exists()) {
            Log::channel('stripe')->info('Stripe webhook: cash_balance.funded já processado', [
                'stripe_cash_balance_transaction_id' => $transactionId,
            ]);

            return;
        }

        $feeConfig = StripePaymentMethodFee::where('payment_method', 'bank_transfer')
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();

        $platformFee = $feeConfig ? $feeConfig->calculatePlatformFee($netAmount) : 0;
        $creditAmount = $netAmount - $platformFee;

        // Determina a data de liquidação esperada (2 dias úteis por defeito para bank transfers)
        $availableOn = $feeConfig
            ? now()->addWeekdays(2)->toDateString()
            : null;

        try {
            StripeTransaction::create([
                'customer_id' => $stripeAccount->customer_id,
                'stripe_payment_method_fee_id' => $feeConfig?->id,
                'type' => TransactionType::BANK_TRANSFER,
                'direction' => TransactionDirection::Credit,
                'payment_method_type' => 'bank_transfer',
                'amount' => $netAmount,
                'stripe_fee' => 0,
                'platform_fee' => $platformFee,
                'net_amount' => $creditAmount,
                'currency' => $currency,
                'status' => 'succeeded',
                'description' => 'Cash balance funded via bank transfer',
                'stripe_cash_balance_transaction_id' => $object['id'] ?? null,
                'bank_transfer_type' => $object['funded']['bank_transfer']['type'] ?? null,
                'bank_transfer_reference' => $object['funded']['bank_transfer']['reference'] ?? null,
                'bank_transfer_sender_name' => $object['funded']['bank_transfer']['eu_bank_transfer']['sender_name'] ?? null,
                'bank_transfer_bic' => $object['funded']['bank_transfer']['eu_bank_transfer']['bic'] ?? null,
                'bank_transfer_iban_last4' => $object['funded']['bank_transfer']['eu_bank_transfer']['iban_last4'] ?? null,
                'bank_transfer_amount' => $netAmount,
                'stripe_metadata' => $object,
                'available_on' => $availableOn,
                'stripe_created_at' => isset($object['created'])
                    ? Carbon::createFromTimestamp($object['created'])
                    : now(),
            ]);

            // Saldo fica pending até o settlement job libertar na data available_on
            $this->incrementBalance($stripeAccount->customer_id, $currency, pending: $creditAmount);
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('Stripe webhook: erro ao processar cash_balance.funded', [
                'stripe_customer_id' => $stripeCustomerId,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        Log::channel('stripe')->info('Stripe webhook: customer.cash_balance.funded', [
            'stripe_customer_id' => $stripeCustomerId,
            'currency' => $currency,
            'net_amount' => $netAmount,
        ]);
    }

    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $transaction = StripeTransaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $transaction) {
            Log::channel('stripe')->warning('Stripe webhook: StripeTransaction não encontrada', ['payment_intent_id' => $intent['id']]);

            return;
        }

        $transaction->update([
            'status' => 'succeeded',
            'stripe_metadata' => $intent,
        ]);

        Log::info('Stripe webhook: payment_intent.succeeded', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);

        $merchantRequest = $this->merchantPaymentRequestRepository
            ->findByStripePaymentIntentId($intent['id']);

        if ($merchantRequest) {
            $this->merchantPaymentRequestRepository->complete($merchantRequest, $transaction->id);
            DispatchMerchantCallback::dispatch($merchantRequest->fresh())->onQueue('default');
        }
    }

    private function createPlatformTransfer(StripeTransaction $sourceTransaction): void
    {
        $platformCustomer = Cache::rememberForever('platform_customer', fn () => Customer::where('type', 'platform')->first());

        if (! $platformCustomer) {
            Log::channel('stripe')->warning('Stripe webhook: conta platform não encontrada para transfer', [
                'transaction_id' => $sourceTransaction->id,
            ]);

            return;
        }

        try {
            StripeTransaction::create([
                'customer_id' => $platformCustomer->id,
                'stripe_payment_method_fee_id' => $sourceTransaction->stripe_payment_method_fee_id,
                'related_transaction_id' => $sourceTransaction->id,
                'type' => TransactionType::P2P,
                'direction' => TransactionDirection::Credit,
                'stripe_payment_intent_id' => $sourceTransaction->stripe_payment_intent_id,
                'stripe_charge_id' => $sourceTransaction->stripe_charge_id,
                'payment_method_type' => $sourceTransaction->payment_method_type,
                'amount' => $sourceTransaction->net_amount,
                'stripe_fee' => 0,
                'platform_fee' => 0,
                'net_amount' => $sourceTransaction->net_amount,
                'currency' => $sourceTransaction->currency,
                'status' => 'succeeded',
                'description' => "Platform transfer from transaction #{$sourceTransaction->id}",
                'stripe_created_at' => now(),
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

    public function settleTransaction(StripeTransaction $transaction): void
    {
        if ($transaction->balance_settled_at !== null) {
            return;
        }

        if ($transaction->net_amount <= 0) {
            $transaction->update(['balance_settled_at' => now()]);

            return;
        }

        $this->incrementBalance(
            $transaction->customer_id,
            $transaction->currency,
            available: $transaction->net_amount,
            pending: -$transaction->net_amount,
        );

        $this->createPlatformTransfer($transaction);

        $transaction->update(['balance_settled_at' => now()]);

        Log::channel('stripe')->info('stripe_service: settleTransaction — saldo libertado', [
            'transaction_id' => $transaction->id,
            'net_amount' => $transaction->net_amount,
            'currency' => $transaction->currency,
            'available_on' => $transaction->available_on,
        ]);
    }

    private function incrementBalance(int $customerId, string $currency, int $available = 0, int $pending = 0): void
    {
        $balance = CustomerBalance::firstOrCreate(
            ['customer_id' => $customerId, 'currency' => strtoupper($currency)],
            ['available' => 0, 'pending' => 0],
        );

        CustomerBalance::where('id', $balance->id)->update([
            'available' => DB::raw("available + {$available}"),
            'pending' => DB::raw("pending + {$pending}"),
        ]);
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $transaction = StripeTransaction::where('stripe_payment_intent_id', $intent['id'])->first();

        if (! $transaction) {
            Log::channel('stripe')->warning('Stripe webhook: StripeTransaction não encontrada', ['payment_intent_id' => $intent['id']]);

            return;
        }

        $transaction->update([
            'status' => 'failed',
            'stripe_metadata' => $intent,
        ]);

        // Reverte crédito aplicado pelo charge.updated (se tiver chegado antes do failed)
        if ($transaction->net_amount > 0) {
            $this->stripePaymentWallet->reverseStripePayment($transaction->fresh());
        }

        Log::info('Stripe webhook: payment_intent.payment_failed', ['pi' => $intent['id'], 'transaction_id' => $transaction->id]);

        $merchantRequest = $this->merchantPaymentRequestRepository
            ->findByStripePaymentIntentId($intent['id']);

        if ($merchantRequest) {
            $this->merchantPaymentRequestRepository->fail($merchantRequest);
            DispatchMerchantCallback::dispatch($merchantRequest->fresh())->onQueue('default');
        }
    }

    private function handleChargeUpdated(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if (! $paymentIntentId) {
            return;
        }

        StripeTransaction::where('stripe_payment_intent_id', $paymentIntentId)
            ->update(['stripe_charge_id' => $charge['id']]);

        $balanceTxnId = $charge['balance_transaction'] ?? null;

        if (! \is_string($balanceTxnId) || ! str_starts_with($balanceTxnId, 'txn_')) {
            return;
        }

        $result = $this->stripeGateway->getBalanceTransaction($balanceTxnId);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: getBalanceTransaction failed', [
                'balance_transaction_id' => $balanceTxnId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        $realStripeFee = $result['data']['fee'] ?? null;
        $availableOn = isset($result['data']['available_on'])
            ? Carbon::createFromTimestamp($result['data']['available_on'])->toDateString()
            : null;

        if ($realStripeFee === null) {
            return;
        }

        $transaction = StripeTransaction::where('stripe_payment_intent_id', $paymentIntentId)
            ->whereNull('related_transaction_id')
            ->first();

        if (! $transaction) {
            return;
        }

        // Classifica o método de pagamento real (para cartões, pelo país do cartão)
        $pmDetails = $charge['payment_method_details'] ?? [];
        $pmType = $pmDetails['type'] ?? null;
        $feeMethod = $this->resolvePaymentMethodCategory($pmType, $pmDetails);

        $feeConfig = StripePaymentMethodFee::where('payment_method', $feeMethod)
            ->where('currency', $transaction->currency)
            ->where('is_active', true)
            ->first();

        $platformFee = $feeConfig ? $feeConfig->calculatePlatformFee($transaction->amount) : 0;
        $realNetAmount = $transaction->amount - $realStripeFee - $platformFee;

        $transaction->update([
            'stripe_payment_method_fee_id' => $feeConfig?->id,
            'payment_method_type' => $feeMethod,
            'stripe_fee' => $realStripeFee,
            'platform_fee' => $platformFee,
            'net_amount' => $realNetAmount,
            'available_on' => $availableOn,
        ]);

        $transaction = $transaction->fresh();

        if ($transaction->type === TransactionType::TOPUP) {
            $allocation = $this->topupFundingPolicy->allocate($transaction);

            $transaction->update([
                'immediate_gross_amount' => $allocation->immediateGrossCents,
                'wallester_funding_net_amount' => $allocation->wallesterFundingNetCents,
                'stripe_deferred_net_amount' => $allocation->stripeDeferredNetCents,
            ]);
        }

        // Credit wallet immediately for the quota Wallester; deferred quota waits for Stripe settlement.
        $this->stripePaymentWallet->creditFromStripePayment($transaction->fresh());

        Log::channel('stripe')->info('stripe_service: charge.updated — fees e categoria aplicados', [
            'payment_intent_id' => $paymentIntentId,
            'balance_transaction_id' => $balanceTxnId,
            'payment_method_category' => $feeMethod,
            'payment_method_fee_config_found' => (bool) $feeConfig,
            'payment_method_fee_id' => $feeConfig?->id,
            'stripe_fee' => $realStripeFee,
            'platform_fee' => $platformFee,
            'net_amount' => $realNetAmount,
            'available_on' => $availableOn,
        ]);

        if ($this->topupFundingPolicy->shouldDispatchPartnerFundingJob($transaction->fresh(), $charge)) {
            FundWallesterTopupFromStripeJob::dispatch($transaction->id);
        }
    }

    private function handleCashBalanceTransactionCreated(array $object): void
    {
        $this->handleCashBalanceFunded($object);
    }

    private function resolvePaymentMethodCategory(string $pmType, array $pmDetails): string
    {
        // Métodos que não são cartão utilizam o próprio identificador.
        if ($pmType !== 'card') {
            return $pmType;
        }

        $country = strtoupper($pmDetails['card']['country'] ?? '');

        $eeaCountries = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
            'FR', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU',
            'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ];

        // Cartões emitidos no EEE
        if (in_array($country, $eeaCountries, true)) {
            return 'card';
        }

        // Qualquer cartão emitido fora do EEE
        return 'card_non_eea';
    }
}

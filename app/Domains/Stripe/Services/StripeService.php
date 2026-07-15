<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Services;

use App\Domains\Merchant\Contracts\MerchantPaymentRequestRepositoryInterface;
use App\Domains\Merchant\Jobs\DispatchMerchantCallback;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Stripe\Services\StripePaymentIntentService;
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
use App\Models\StripePaymentIntent;
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
        protected StripePaymentIntentService $paymentIntents,
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

        $data = $this->buildFundingInstructionsPayload($customer, $currency, $fundingType);

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

    /** @return array<string, string> */
    private function buildFundingInstructionsPayload(Customer $customer, string $currency, string $bankTransferType): array
    {
        $data = [
            'funding_type' => 'bank_transfer',
            'currency' => $currency,
            'bank_transfer[type]' => $bankTransferType,
        ];

        if ($bankTransferType === 'eu_bank_transfer') {
            $data['bank_transfer[eu_bank_transfer][country]'] = $this->resolveEuBankTransferCountry($customer);
        }

        return $data;
    }

    private function resolveEuBankTransferCountry(Customer $customer): string
    {
        $allowedCountries = ['DE', 'FR', 'IE', 'NL'];

        $customer->loadMissing(['documents', 'corporateDocuments']);

        $candidates = [
            $customer->documents->first()?->issuing_state,
            $customer->corporateDocuments->first()?->country_code,
        ];

        foreach ($candidates as $candidate) {
            $country = strtoupper(trim((string) $candidate));

            if (in_array($country, $allowedCountries, true)) {
                return $country;
            }
        }

        return 'FR';
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
            $paymentIntent = $this->paymentIntents->createFromStripeResponse(
                customer: $customer,
                intent: $intent,
                type: $isTopup ? TransactionType::TOPUP : TransactionType::from($feeConfig->payment_method),
                purpose: StripePaymentIntentPurpose::Topup,
                amount: $amount,
                currency: $currency,
                feeConfig: $feeConfig,
                paymentMethodType: $feeConfig->payment_method,
                description: $description,
            );
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('stripe_service: payment_intent_create_failed', [
                'customer_id' => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a intenção de pagamento. Payment intent criado no Stripe: '.$intent['id']
            );
        }

        return [
            'payment_intent' => $paymentIntent,
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
            $paymentIntent = $this->paymentIntents->createFromStripeResponse(
                customer: $customer,
                intent: $intent,
                type: TransactionType::CARD_PRESENT,
                purpose: StripePaymentIntentPurpose::Terminal,
                amount: $amount,
                currency: $currency,
                paymentMethodType: 'card_present',
                description: $description,
                tpaId: $tpa?->id,
            );
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('stripe_service: terminal_payment_intent_create_failed', [
                'customer_id' => $customer->id,
                'stripe_payment_intent_id' => $intent['id'],
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                'Erro ao registrar a intenção de pagamento. Payment intent criado no Stripe: '.$intent['id']
            );
        }

        return [
            'payment_intent' => $paymentIntent,
            'client_secret' => $intent['client_secret'],
        ];
    }

    public function captureTerminalPayment(StripePaymentIntent $paymentIntent): array
    {
        if ($paymentIntent->payment_method_type !== 'card_present') {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Esta intenção não é um pagamento terminal.'
            );
        }

        $status = $paymentIntent->status;

        if (! $status->isCapturable() && $status !== StripePaymentIntentStatus::RequiresPaymentMethod) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                422,
                'Intenção de pagamento não está num estado capturável.'
            );
        }

        $result = $this->stripeGateway->capturePaymentIntent($paymentIntent->stripe_payment_intent_id);

        if ($result['status'] !== 'success') {
            Log::channel('stripe')->warning('stripe_service: captureTerminalPayment', [
                'payment_intent_id' => $paymentIntent->id,
                'stripe_payment_intent_id' => $paymentIntent->stripe_payment_intent_id,
                'message' => $result['message'],
            ]);
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                502,
                $result['message'] ?? 'Erro ao capturar pagamento terminal.'
            );
        }

        Log::channel('stripe')->info('stripe_service: captureTerminalPayment — captura solicitada', [
            'payment_intent_id' => $paymentIntent->id,
            'stripe_payment_intent_id' => $paymentIntent->stripe_payment_intent_id,
        ]);

        return $result['data'];
    }

    public function showPaymentIntent(Customer $customer, int $id): StripePaymentIntent
    {
        $paymentIntent = $this->paymentIntents->findForCustomer($customer, $id);

        if (! $paymentIntent) {
            throw new ApiException(
                ErrorCode::STRIPE_PAYMENT_INTENT_FAILED,
                404,
                'Intenção de pagamento não encontrada.'
            );
        }

        return $paymentIntent;
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
            'payment_intent.canceled' => $this->handlePaymentIntentCanceled($object),
            'charge.updated' => $this->handleChargeUpdated($object),
            'customer_cash_balance_settings.updated', 'customer.cash_balance_settings.updated' => $this->integrationAccounts->handleStripeWebhook($eventType, $object),
            'customer_cash_balance_transaction.created', 'customer.cash_balance_transaction.created' => $this->handleCashBalanceTransactionCreated($object),
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
        if (($object['type'] ?? null) !== 'funded') {
            return;
        }

        $stripeCustomerId = $object['customer'] ?? null;
        $currency = strtoupper((string) ($object['currency'] ?? ''));
        $netAmount = (int) ($object['net_amount'] ?? 0);
        $cashBalanceTransactionId = $object['id'] ?? null;

        if (! $stripeCustomerId || $currency === '' || $netAmount <= 0 || ! $cashBalanceTransactionId) {
            Log::channel('stripe')->warning('Stripe webhook: cash_balance.funded com dados inválidos', $object);

            return;
        }

        if ($this->paymentIntents->findByCashBalanceTransactionId($cashBalanceTransactionId)) {
            Log::channel('stripe')->info('Stripe webhook: cash_balance.funded já processado', [
                'stripe_cash_balance_transaction_id' => $cashBalanceTransactionId,
            ]);

            return;
        }

        if (StripeTransaction::where('stripe_cash_balance_transaction_id', $cashBalanceTransactionId)->exists()) {
            Log::channel('stripe')->info('Stripe webhook: cash_balance.funded já processado', [
                'stripe_cash_balance_transaction_id' => $cashBalanceTransactionId,
            ]);

            return;
        }

        $stripeAccount = CustomerStripeAccount::where('stripe_customer_id', $stripeCustomerId)->first();

        if (! $stripeAccount) {
            Log::channel('stripe')->warning('Stripe webhook: CustomerStripeAccount não encontrada', [
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            return;
        }

        $feeConfig = StripePaymentMethodFee::where('payment_method', 'bank_transfer')
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();

        $paymentIntentResult = $this->stripeGateway->createPaymentIntent([
            'amount' => $netAmount,
            'currency' => strtolower($currency),
            'customer' => $stripeCustomerId,
            'payment_method_types' => ['customer_balance'],
            'payment_method_data' => ['type' => 'customer_balance'],
            'confirm' => true,
            'description' => 'Bank transfer top-up',
            'metadata' => [
                'customer_id' => (string) $stripeAccount->customer_id,
                'purpose' => 'bank_transfer_topup',
                'cash_balance_transaction_id' => $cashBalanceTransactionId,
            ],
        ]);

        if ($paymentIntentResult['status'] !== 'success') {
            Log::channel('stripe')->error('Stripe webhook: falha ao criar PaymentIntent para cash balance', [
                'stripe_customer_id' => $stripeCustomerId,
                'cash_balance_transaction_id' => $cashBalanceTransactionId,
                'message' => $paymentIntentResult['message'] ?? null,
            ]);

            return;
        }

        $intent = $paymentIntentResult['data'];
        $intentStatus = $intent['status'] ?? 'pending';

        if (in_array($intentStatus, ['requires_payment_method', 'requires_action', 'canceled'], true)) {
            Log::channel('stripe')->error('Stripe webhook: PaymentIntent de cash balance não confirmado', [
                'stripe_customer_id' => $stripeCustomerId,
                'cash_balance_transaction_id' => $cashBalanceTransactionId,
                'stripe_payment_intent_id' => $intent['id'] ?? null,
                'status' => $intentStatus,
            ]);

            return;
        }

        try {
            $customer = Customer::findOrFail($stripeAccount->customer_id);

            $paymentIntentRecord = $this->paymentIntents->createFromStripeResponse(
                customer: $customer,
                intent: $intent,
                type: TransactionType::TOPUP,
                purpose: StripePaymentIntentPurpose::BankTransferTopup,
                amount: $netAmount,
                currency: $currency,
                feeConfig: $feeConfig,
                paymentMethodType: 'bank_transfer',
                description: 'Bank transfer top-up via VIBAN',
                cashBalanceTransactionId: $cashBalanceTransactionId,
            );

            $transaction = $this->paymentIntents->resolveOrCreateTransaction(
                paymentIntent: $paymentIntentRecord,
                transactionStatus: $intentStatus === 'succeeded' ? 'succeeded' : 'pending',
                intentPayload: $intent,
                extraTransactionAttributes: [
                    'bank_transfer_type' => $object['funded']['bank_transfer']['type'] ?? null,
                    'bank_transfer_reference' => $object['funded']['bank_transfer']['reference'] ?? null,
                    'bank_transfer_sender_name' => $object['funded']['bank_transfer']['eu_bank_transfer']['sender_name'] ?? null,
                    'bank_transfer_bic' => $object['funded']['bank_transfer']['eu_bank_transfer']['bic'] ?? null,
                    'bank_transfer_iban_last4' => $object['funded']['bank_transfer']['eu_bank_transfer']['iban_last4'] ?? null,
                    'bank_transfer_amount' => $netAmount,
                    'stripe_metadata' => $object,
                ],
            );
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('Stripe webhook: erro ao registar transação de cash balance', [
                'stripe_customer_id' => $stripeCustomerId,
                'cash_balance_transaction_id' => $cashBalanceTransactionId,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        Log::channel('stripe')->info('Stripe webhook: cash balance reconciliado com PaymentIntent', [
            'transaction_id' => $transaction->id,
            'stripe_payment_intent_id' => $intent['id'],
            'stripe_customer_id' => $stripeCustomerId,
            'cash_balance_transaction_id' => $cashBalanceTransactionId,
            'net_amount' => $netAmount,
            'currency' => $currency,
            'payment_intent_status' => $intentStatus,
        ]);
    }

    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $paymentIntent = $this->paymentIntents->findByStripeId($intent['id']);

        if (! $paymentIntent) {
            Log::channel('stripe')->warning('Stripe webhook: StripePaymentIntent não encontrada', ['payment_intent_id' => $intent['id']]);

            return;
        }

        $this->paymentIntents->syncStatusFromStripePayload($paymentIntent, $intent);

        $transaction = $this->paymentIntents->resolveOrCreateTransaction(
            paymentIntent: $paymentIntent->fresh(),
            transactionStatus: 'succeeded',
            intentPayload: $intent,
        );

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
        $paymentIntent = $this->paymentIntents->findByStripeId($intent['id']);

        if (! $paymentIntent) {
            Log::channel('stripe')->warning('Stripe webhook: StripePaymentIntent não encontrada', ['payment_intent_id' => $intent['id']]);

            return;
        }

        $this->paymentIntents->markFailed($paymentIntent, $intent);

        $transaction = $this->paymentIntents->resolveOrCreateTransaction(
            paymentIntent: $paymentIntent->fresh(),
            transactionStatus: 'failed',
            intentPayload: $intent,
        );

        $transaction->update([
            'status' => 'failed',
            'stripe_metadata' => $intent,
        ]);

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

    private function handlePaymentIntentCanceled(array $intent): void
    {
        $paymentIntent = $this->paymentIntents->findByStripeId($intent['id']);

        if (! $paymentIntent) {
            Log::channel('stripe')->warning('Stripe webhook: StripePaymentIntent não encontrada', ['payment_intent_id' => $intent['id']]);

            return;
        }

        $this->paymentIntents->markCanceled($paymentIntent, $intent);

        Log::info('Stripe webhook: payment_intent.canceled', ['pi' => $intent['id'], 'payment_intent_id' => $paymentIntent->id]);
    }

    private function handleChargeUpdated(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if (! $paymentIntentId) {
            return;
        }

        $paymentIntentRecord = $this->paymentIntents->findByStripeId($paymentIntentId);

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

        if (! $transaction && $paymentIntentRecord) {
            $transaction = $this->paymentIntents->resolveOrCreateTransaction(
                paymentIntent: $paymentIntentRecord,
                transactionStatus: 'succeeded',
            );
        }

        if (! $transaction) {
            Log::channel('stripe')->warning('stripe_service: charge.updated sem transação resolvida', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        // Classifica o método de pagamento real (para cartões, pelo país do cartão)
        $pmDetails = $charge['payment_method_details'] ?? [];
        $pmType = $pmDetails['type'] ?? null;
        $feeMethod = filled($transaction->stripe_cash_balance_transaction_id)
            ? $transaction->payment_method_type
            : $this->resolvePaymentMethodCategory($pmType, $pmDetails);

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

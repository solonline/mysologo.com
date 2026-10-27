<?php

namespace App\Domains\Wise\Services;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Domains\Wise\Enums\WiseTransferStatus;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWiseBeneficiary;
use App\Models\WiseTransfer;
use Illuminate\Support\Str;
use Throwable;

class WiseTransferService
{
    public function __construct(
        private readonly WiseGatewayInterface $gateway,
        private readonly RailMirrorService $railMirror,
        private readonly WiseBeneficiaryService $beneficiaries,
        private readonly DeviceAuthService $deviceAuth,
        private readonly FundingPartnerMovementService $movements,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quote(Customer $customer, array $input): array
    {
        $quoted = $this->quotePayIn($customer, $input);
        $fee = $quoted['pay_in']['fee'];

        return [
            'quote_id' => $quoted['quote_id'],
            'beneficiary_id' => $quoted['beneficiary']->wise_recipient_id,
            'source_currency' => $quoted['source_currency'],
            'amount_cents' => $quoted['amount_cents'],
            'source_value' => $quoted['pay_in']['source_amount'],
            'target_currency' => $quoted['target_currency'],
            'target_value' => $quoted['quote']['targetAmount'] ?? null,
            'fee' => $fee['amount'],
            'fee_currency' => $fee['currency'] ?? $quoted['source_currency'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(Customer $customer, array $input): array
    {
        $this->deviceAuth->verifyPin(
            $customer,
            (string) $input['device_token'],
            (string) $input['pin'],
        );

        $quoted = $this->quotePayIn($customer, $input);
        $sourceCurrency = $quoted['source_currency'];
        $targetCurrency = $quoted['target_currency'];
        $beneficiary = $quoted['beneficiary'];
        $amountCents = $quoted['amount_cents'];
        $quote = $quoted['quote'];
        $quoteId = $quoted['quote_id'];
        $payIn = $quoted['pay_in'];
        $fee = $payIn['fee'];

        $customerTransactionId = (string) Str::uuid();
        $idempotencyKey = 'wise_transfer:'.$customerTransactionId;

        $this->railMirror->reserveForWiseTransfer(
            customer: $customer,
            currency: $sourceCurrency,
            amountCents: $amountCents,
            idempotencyKey: $idempotencyKey,
            feeCents: $fee['cents'],
            feeCurrency: $fee['currency'] ?? $sourceCurrency,
        );

        $reference = $this->internalReference();
        $wiseTransfer = null;

        try {
            $batch = $this->gateway->createBatchGroup($sourceCurrency, $this->batchName($reference));
            $batchGroupId = (string) ($batch['id'] ?? '');

            if ($batchGroupId === '') {
                throw new ApiException(
                    'wise_create_batch_group_failed',
                    502,
                    'Wise did not return a batch group id.',
                    'wise',
                );
            }

            $transfer = $this->gateway->createBatchGroupTransfer($batchGroupId, [
                'targetAccount' => $beneficiary->wise_recipient_id,
                'quoteUuid' => $quoteId,
                'customerTransactionId' => $customerTransactionId,
                'details' => [
                    'reference' => $reference,
                ],
            ]);

            $transferId = $transfer['id'] ?? null;

            if ($transferId === null || $transferId === '') {
                throw new ApiException(
                    'wise_create_transfer_failed',
                    502,
                    'Wise did not return a transfer id.',
                    'wise',
                );
            }

            $current = $this->gateway->getBatchGroup($batchGroupId);
            $version = $current['version'] ?? $batch['version'] ?? null;

            if (! is_numeric($version)) {
                throw new ApiException(
                    'wise_complete_batch_group_failed',
                    502,
                    'Wise did not return a batch group version.',
                    'wise',
                );
            }

            $completed = $this->gateway->completeBatchGroup($batchGroupId, (int) $version);
            $status = WiseTransferStatus::tryFrom(strtolower((string) ($transfer['status'] ?? '')))
                ?? WiseTransferStatus::IncomingPaymentWaiting;

            $wiseTransfer = WiseTransfer::query()->create([
                'customer_id' => $customer->id,
                'wise_transfer_id' => (int) $transferId,
                'wise_batch_group_id' => $batchGroupId,
                'wise_recipient_id' => $beneficiary->wise_recipient_id,
                'beneficiary_name' => $beneficiary->account_holder_name,
                'reference' => $reference,
                'currency' => $sourceCurrency,
                'amount_cents' => $amountCents,
                'fee_cents' => $fee['cents'],
                'fee_currency' => $fee['currency'] ?? $sourceCurrency,
                'idempotency_key' => $idempotencyKey,
                'status' => $status,
            ]);
        } catch (Throwable $exception) {
            $this->railMirror->releaseWiseTransferReservation(
                customer: $customer,
                currency: $sourceCurrency,
                amountCents: $amountCents,
                idempotencyKey: $idempotencyKey,
            );

            throw $exception;
        }

        $fundingAccount = $customer->wallesterAccountFor($sourceCurrency);

        if ($fundingAccount instanceof CustomerWallesterAccount && $wiseTransfer instanceof WiseTransfer) {
            $this->movements->recordPendingWiseMirror($fundingAccount, $wiseTransfer);
        }

        return [
            'quote_id' => $quoteId,
            'transfer_id' => (int) $transferId,
            'batch_group_id' => $batchGroupId,
            'beneficiary_id' => $beneficiary->wise_recipient_id,
            'reference' => $reference,
            'customer_transaction_id' => $customerTransactionId,
            'status' => $completed['status'] ?? $status,
            'source_currency' => $transfer['sourceCurrency'] ?? $sourceCurrency,
            'source_value' => $payIn['source_amount'],
            'target_currency' => $transfer['targetCurrency'] ?? $targetCurrency,
            'target_value' => $transfer['targetValue'] ?? ($quote['targetAmount'] ?? null),
            'amount_cents' => $amountCents,
            'fee' => $fee['amount'],
            'fee_currency' => $fee['currency'] ?? $sourceCurrency,
        ];
    }

    private function internalReference(): string
    {
        return substr(strtoupper((string) Str::ulid()), 0, 16);
    }

    private function batchName(string $reference): string
    {
        $name = trim($reference);

        if ($name === '') {
            return 'Wise transfer';
        }

        return mb_substr($name, 0, 100);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     quote: array<string, mixed>,
     *     quote_id: string,
     *     beneficiary: CustomerWiseBeneficiary,
     *     source_currency: string,
     *     target_currency: string,
     *     amount_cents: int,
     *     pay_in: array{source_amount: float, fee: array{amount: float|null, cents: int|null, currency: string|null}}
     * }
     */
    private function quotePayIn(Customer $customer, array $input): array
    {
        $sourceCurrency = strtoupper((string) $input['source_currency']);
        $beneficiary = $this->resolveBeneficiary($customer, $input);
        $targetCurrency = strtoupper((string) $beneficiary->currency);
        $amountCents = (int) $input['source_amount'];

        $quote = $this->gateway->createQuote([
            'sourceCurrency' => $sourceCurrency,
            'targetCurrency' => $targetCurrency,
            'targetAccount' => $beneficiary->wise_recipient_id,
            'preferredPayIn' => 'BALANCE',
            'sourceAmount' => $this->centsToMajor($amountCents),
        ]);
        $quoteId = (string) ($quote['id'] ?? '');

        if ($quoteId === '') {
            throw new ApiException(
                'wise_create_quote_failed',
                502,
                'Wise did not return a quote id.',
                'wise',
            );
        }

        return [
            'quote' => $quote,
            'quote_id' => $quoteId,
            'beneficiary' => $beneficiary,
            'source_currency' => $sourceCurrency,
            'target_currency' => $targetCurrency,
            'amount_cents' => $amountCents,
            'pay_in' => $this->balancePayIn($quote),
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array{source_amount: float, fee: array{amount: float|null, cents: int|null, currency: string|null}}
     */
    private function balancePayIn(array $quote): array
    {
        $payOut = isset($quote['payOut']) ? (string) $quote['payOut'] : null;
        $options = $quote['paymentOptions'] ?? null;

        if (! is_array($options)) {
            throw new ApiException(
                'wise_balance_payin_unavailable',
                422,
                'Wise quote has no balance pay-in option.',
                'wise',
            );
        }

        foreach ($options as $option) {
            if (! is_array($option) || ($option['payIn'] ?? null) !== 'BALANCE' || ($option['disabled'] ?? false) === true) {
                continue;
            }

            if ($payOut !== null && $payOut !== '' && isset($option['payOut']) && (string) $option['payOut'] !== $payOut) {
                continue;
            }

            $sourceAmount = $option['sourceAmount'] ?? null;

            if (! is_numeric($sourceAmount)) {
                break;
            }

            return [
                'source_amount' => (float) $sourceAmount,
                'fee' => $this->feeFromOption($option),
            ];
        }

        throw new ApiException(
            'wise_balance_payin_unavailable',
            422,
            'Wise quote has no balance pay-in option.',
            'wise',
        );
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array{amount: float|null, cents: int|null, currency: string|null}
     */
    private function feeFromOption(array $option): array
    {
        $priceTotal = $option['price']['total'] ?? null;
        $amount = null;
        $currency = null;

        if (is_array($priceTotal)) {
            $value = $priceTotal['value'] ?? null;

            if (is_array($value) && is_numeric($value['amount'] ?? null)) {
                $amount = (float) $value['amount'];
                $currency = isset($value['currency']) ? strtoupper((string) $value['currency']) : null;
            } elseif (is_numeric($priceTotal['amount'] ?? null)) {
                $amount = (float) $priceTotal['amount'];
                $currency = isset($priceTotal['currency']) ? strtoupper((string) $priceTotal['currency']) : null;
            }
        } elseif (is_numeric($priceTotal)) {
            $amount = (float) $priceTotal;
        }

        return [
            'amount' => $amount,
            'cents' => $amount === null ? null : (int) round($amount * 100),
            'currency' => $currency,
        ];
    }

    private function centsToMajor(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveBeneficiary(Customer $customer, array $input): CustomerWiseBeneficiary
    {
        if (isset($input['beneficiary_id'])) {
            return $this->beneficiaryFor($customer, (int) $input['beneficiary_id']);
        }

        /** @var array<string, mixed> $payload */
        $payload = $input['beneficiary'];

        return $this->beneficiaries->create($customer, $payload)['beneficiary'];
    }

    private function beneficiaryFor(Customer $customer, int $wiseRecipientId): CustomerWiseBeneficiary
    {
        $beneficiary = $customer->wiseBeneficiaries()
            ->where('wise_recipient_id', $wiseRecipientId)
            ->first();

        if (! $beneficiary instanceof CustomerWiseBeneficiary) {
            throw new ApiException(
                'wise_beneficiary_not_found',
                422,
                'This beneficiary is not linked to the customer.',
                'wise',
            );
        }

        return $beneficiary;
    }
}

<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WalletTransactionService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected DeviceAuthService $deviceAuthService,
        protected LedgerService $ledgerService,
        protected FundingPartnerMovementService $fundingPartnerMovements,
    ) {}

    public function list(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->transactionRepository->getPaginated($customer, $perPage);
    }

    public function show(Customer $customer, int $id): StripeTransaction
    {
        $transaction = $this->transactionRepository->findForCustomer($customer, $id);

        if (! $transaction) {
            throw new NotFoundHttpException('Transaction not found.');
        }

        return $transaction;
    }

    /** @return array{debit: StripeTransaction, credit: StripeTransaction} */
    public function p2pTransfer(
        Customer $sender,
        string $recipientUsername,
        int $amount,
        string $currency,
        string $deviceToken,
        string $pin,
        ?string $description = null,
    ): array {
        $this->deviceAuthService->verifyPin($sender, $deviceToken, $pin);

        if ($sender->type === 'platform') {
            throw new ApiException(ErrorCode::ACCOUNT_NOT_ACTIVE, 403, 'Conta platform não pode usar P2P.');
        }

        $currency = strtoupper($currency);

        $recipient = $this->customerRepository->findByUsername($recipientUsername, excludeId: $sender->id);

        if (! $recipient) {
            throw new ApiException(ErrorCode::CUSTOMER_NOT_FOUND, 404, 'Destinatário não encontrado.');
        }

        if ($sender->id === $recipient->id) {
            throw new ApiException(ErrorCode::P2P_SELF_TRANSFER, 422, 'Não é possível transferir para si mesmo.');
        }

        $idempotencyKey = 'p2p:'.Str::uuid()->toString();
        $description ??= 'P2P transfer';

        $this->fundingPartnerMovements->transferBetweenCustomers(
            from: $sender,
            to: $recipient,
            amountCents: $amount,
            currency: $currency,
            description: $description,
            idempotencyKey: $idempotencyKey,
        );

        return DB::transaction(function () use ($sender, $recipient, $amount, $currency, $description) {
            $base = [
                'type' => TransactionType::P2P,
                'payment_method_type' => 'p2p',
                'amount' => $amount,
                'stripe_fee' => 0,
                'platform_fee' => 0,
                'net_amount' => $amount,
                'currency' => $currency,
                'status' => 'succeeded',
                'description' => $description,
                'stripe_created_at' => now(),
            ];

            $debit = $this->transactionRepository->create([
                ...$base,
                'customer_id' => $sender->id,
                'direction' => TransactionDirection::Debit,
            ]);

            $credit = $this->transactionRepository->create([
                ...$base,
                'customer_id' => $recipient->id,
                'direction' => TransactionDirection::Credit,
                'related_transaction_id' => $debit->id,
            ]);

            $debit->update(['related_transaction_id' => $credit->id]);

            $this->ledgerService->record(
                customerId: $sender->id,
                currency: $currency,
                entryType: LedgerEntryType::P2P_SEND,
                amount: $amount,
                reference: $debit,
                description: $description,
            );

            $this->ledgerService->record(
                customerId: $recipient->id,
                currency: $currency,
                entryType: LedgerEntryType::P2P_RECEIVE,
                amount: $amount,
                reference: $credit,
                description: $description,
            );

            return [
                'debit' => $debit->fresh(),
                'credit' => $credit,
            ];
        });
    }
}

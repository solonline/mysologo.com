<?php

namespace App\Services;

use App\Contracts\CustomerRepositoryInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Enums\LedgerEntryType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\DeviceAuthService;
use App\Services\LedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected DeviceAuthService $deviceAuthService,
        protected LedgerService $ledgerService,
    ) {}

    public function list(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->transactionRepository->getPaginated($customer, $perPage);
    }

    public function show(Customer $customer, int $id): Transaction
    {
        $transaction = $this->transactionRepository->findForCustomer($customer, $id);

        if (! $transaction) {
            throw new NotFoundHttpException('Transaction not found.');
        }

        return $transaction;
    }

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

        $currency = strtoupper($currency);

        $recipient = $this->customerRepository->findByUsername($recipientUsername, excludeId: $sender->id);

        if (! $recipient) {
            throw new ApiException(ErrorCode::CUSTOMER_NOT_FOUND, 404, 'Destinatário não encontrado.');
        }

        if ($sender->id === $recipient->id) {
            throw new ApiException(ErrorCode::P2P_SELF_TRANSFER, 422, 'Não é possível transferir para si mesmo.');
        }

        $available = $this->transactionRepository->getAvailableBalance($sender->id, $currency);

        if ($available < $amount) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo insuficiente. Disponível: {$available} {$currency}."
            );
        }

        return DB::transaction(function () use ($sender, $recipient, $amount, $currency, $description) {
            $base = [
                'type'                => TransactionType::P2P,
                'payment_method_type' => 'p2p',
                'amount'              => $amount,
                'stripe_fee'          => 0,
                'platform_fee'        => 0,
                'net_amount'          => $amount,
                'currency'            => $currency,
                'status'              => 'succeeded',
                'description'         => $description,
                'stripe_created_at'   => now(),
            ];

            $debit = $this->transactionRepository->create([
                ...$base,
                'customer_id' => $sender->id,
                'direction'   => TransactionDirection::Debit,
            ]);

            $credit = $this->transactionRepository->create([
                ...$base,
                'customer_id'            => $recipient->id,
                'direction'              => TransactionDirection::Credit,
                'related_transaction_id' => $debit->id,
            ]);

            $debit->update(['related_transaction_id' => $credit->id]);

            $this->ledgerService->record(
                customerId:  $sender->id,
                currency:    $currency,
                entryType:   LedgerEntryType::P2P_SEND,
                amount:      $amount,
                reference:   $debit,
                description: $description,
            );

            $this->ledgerService->record(
                customerId:  $recipient->id,
                currency:    $currency,
                entryType:   LedgerEntryType::P2P_RECEIVE,
                amount:      $amount,
                reference:   $credit,
                description: $description,
            );

            return [
                'debit'  => $debit->fresh(),
                'credit' => $credit,
            ];
        });
    }
}

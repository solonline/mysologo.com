<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\Services\CustomerFavoriteService;
use App\Domains\Wallet\Contracts\TransactionRepositoryInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\FundingPartnerMovement;
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
        protected CustomerFavoriteService $favorites,
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
        ?string $idempotencyKey = null,
        bool $favorite = false,
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

        $movementKey = $this->p2pMovementKey($idempotencyKey);
        $description ??= 'P2P transfer';

        $existing = $this->findExistingP2pPair($movementKey);
        if ($existing === null) {
            $this->fundingPartnerMovements->transferBetweenCustomers(
                from: $sender,
                to: $recipient,
                amountCents: $amount,
                currency: $currency,
                description: $description,
                idempotencyKey: $movementKey,
            );

            $existing = $this->findExistingP2pPair($movementKey);
        }

        $result = $existing ?? DB::transaction(function () use ($sender, $recipient, $amount, $currency, $description, $movementKey) {
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

            $this->attachP2pMovementReferences($movementKey, $debit, $credit);

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

        if ($favorite) {
            $this->favorites->add($sender, $recipient);
        }

        return $result;
    }

    private function p2pMovementKey(?string $idempotencyKey): string
    {
        $key = trim((string) $idempotencyKey);

        if ($key === '') {
            $key = (string) Str::uuid();
        }

        return str_starts_with($key, 'p2p:') ? $key : 'p2p:'.$key;
    }

    /**
     * @return array{debit: StripeTransaction, credit: StripeTransaction}|null
     */
    private function findExistingP2pPair(string $movementKey): ?array
    {
        $outMovement = FundingPartnerMovement::query()
            ->where('idempotency_key', $movementKey.':out')
            ->first();

        if (! $outMovement instanceof FundingPartnerMovement) {
            return null;
        }

        $debit = $outMovement->reference;
        if (! $debit instanceof StripeTransaction) {
            return null;
        }

        $credit = $debit->related_transaction_id
            ? StripeTransaction::query()->find($debit->related_transaction_id)
            : null;

        if (! $credit instanceof StripeTransaction) {
            return null;
        }

        return [
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    private function attachP2pMovementReferences(
        string $movementKey,
        StripeTransaction $debit,
        StripeTransaction $credit,
    ): void {
        FundingPartnerMovement::query()
            ->where('idempotency_key', $movementKey.':out')
            ->update([
                'reference_type' => $debit->getMorphClass(),
                'reference_id' => $debit->id,
            ]);

        FundingPartnerMovement::query()
            ->where('idempotency_key', $movementKey.':in')
            ->update([
                'reference_type' => $credit->getMorphClass(),
                'reference_id' => $credit->id,
            ]);
    }
}

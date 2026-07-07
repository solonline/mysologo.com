<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;

class WallesterTransactionService
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected WallesterAccountRepositoryInterface $wallesterAccountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_records_number: int, transactions: array<int, array<string, mixed>>, from_record: int, records_count: int}
     */
    public function listForCustomer(Customer $customer, array $filters): array
    {
        $account = $this->resolveActiveAccount($customer);
        $currency = (string) ($account->funding_currency ?? 'EUR');
        $query = $this->buildWallesterQuery($filters);
        $auditUserId = (string) $customer->id;

        if (isset($filters['card_id'])) {
            $card = $this->resolveCustomerCard($customer, (int) $filters['card_id']);
            $response = $this->gateway->forCurrency($currency)->listCardTransactions($card->wallester_card_id, $query, $auditUserId);
        } else {
            $response = $this->gateway->forCurrency($currency)->listAccountTransactions($account->wallester_account_id, $query, $auditUserId);
        }

        return [
            'total_records_number' => $response['total_records_number'],
            'transactions' => $response['transactions'],
            'from_record' => $query['from_record'],
            'records_count' => $query['records_count'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showForCustomer(Customer $customer, string $wallesterTransactionId): array
    {
        $account = $this->resolveActiveAccount($customer);
        $transaction = $this->gateway
            ->forCurrency((string) ($account->funding_currency ?? 'EUR'))
            ->getTransaction($wallesterTransactionId, (string) $customer->id);

        $this->ensureCustomerOwnsTransaction($customer, $account, $transaction);

        return $transaction;
    }

    private function resolveActiveAccount(Customer $customer): CustomerWallesterAccount
    {
        $account = $this->wallesterAccountRepository->findByCustomerId($customer->id);

        if ($account === null) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Wallester account not found for this customer.',
            );
        }

        if ($account->status !== IntegrationAccountStatus::ACTIVE) {
            throw new ApiException(
                ErrorCode::ACCOUNT_NOT_ACTIVE,
                422,
                'Wallester account is not active.',
            );
        }

        return $account;
    }

    private function resolveCustomerCard(Customer $customer, int $cardId): CustomerWallesterCard
    {
        $card = CustomerWallesterCard::query()
            ->whereKey($cardId)
            ->whereHas('wallesterAccount', fn ($query) => $query->where('customer_id', $customer->id))
            ->first();

        if ($card === null) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                403,
                'This card does not belong to the authenticated customer.',
            );
        }

        return $card;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from_record: int, records_count: int, from_date?: string, to_date?: string}
     */
    private function buildWallesterQuery(array $filters): array
    {
        $query = [
            'from_record' => (int) ($filters['from_record'] ?? 0),
            'records_count' => (int) ($filters['records_count'] ?? 15),
        ];

        if (isset($filters['from_date'])) {
            $query['from_date'] = (string) $filters['from_date'];
        }

        if (isset($filters['to_date'])) {
            $query['to_date'] = (string) $filters['to_date'];
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function ensureCustomerOwnsTransaction(
        Customer $customer,
        CustomerWallesterAccount $account,
        array $transaction,
    ): void {
        $transactionAccountId = (string) ($transaction['account_id'] ?? '');

        if ($transactionAccountId !== '' && $transactionAccountId === $account->wallester_account_id) {
            return;
        }

        $transactionCardId = (string) ($transaction['card_id'] ?? '');

        if ($transactionCardId !== '') {
            $ownsCard = CustomerWallesterCard::query()
                ->where('wallester_card_id', $transactionCardId)
                ->whereHas('wallesterAccount', fn ($query) => $query->where('customer_id', $customer->id))
                ->exists();

            if ($ownsCard) {
                return;
            }
        }

        throw new ApiException(
            ErrorCode::INVALID_CREDENTIALS,
            403,
            'This transaction does not belong to the authenticated customer.',
        );
    }
}

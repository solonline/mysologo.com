<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Contracts\FundingPartnerTransferInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use RuntimeException;

class WallesterFundingTransferDriver implements FundingPartnerTransferInterface
{
    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected PlatformMasterCurrencyResolver $currencyResolver,
        protected WallesterSettingsService $wallesterSettings,
    ) {}

    public function provider(): string
    {
        return IntegrationProvider::WALLESTER->value;
    }

    public function transferBetweenCustomers(
        Customer $from,
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
    ): void {
        $routingCurrency = $this->currencyResolver->resolve($currency);

        $fromAccount = $this->requireActiveAccount($from, $routingCurrency);
        $toAccount = $this->requireActiveAccount($to, $routingCurrency);

        $this->executeTransfer(
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            amountCents: $amountCents,
            description: $description,
            auditUserId: (string) $from->id,
        );
    }

    public function transferFromMasterToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        $routingCurrency = $this->currencyResolver->resolve($currency);
        $masterAccount = $this->getPlatformMasterAccount($routingCurrency);
        $toAccount = $this->requireActiveAccount($to, $routingCurrency);

        $this->executeTransfer(
            fromAccount: $masterAccount,
            toAccount: $toAccount,
            amountCents: $amountCents,
            description: $description,
            auditUserId: 'platform',
        );
    }

    public function getAvailableBalance(Customer $customer, string $currency): int
    {
        $routingCurrency = $this->currencyResolver->resolve($currency);
        $account = $this->requireActiveAccount($customer, $routingCurrency);

        $response = $this->gateway
            ->forCurrency($routingCurrency)
            ->getAccount($account->wallester_account_id, (string) $customer->id);

        $account->syncBalancesFromWallesterResponse(
            $response,
            fn (float|int|string|null $amount): int => $this->amountToCents($amount),
        );

        return $this->amountToCents($response['available_amount'] ?? $response['balance'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function executeTransfer(
        CustomerWallesterAccount $fromAccount,
        CustomerWallesterAccount $toAccount,
        int $amountCents,
        string $description,
        string $auditUserId,
    ): array {
        $currency = (string) ($fromAccount->funding_currency ?? 'EUR');

        return $this->gateway->forCurrency($currency)->accountTransfer([
            'from_account_id' => $fromAccount->wallester_account_id,
            'to_account_id' => $toAccount->wallester_account_id,
            'amount' => round($amountCents / 100, 2),
            'description' => $description,
        ], $auditUserId);
    }

    public function getPlatformMasterAccount(string $currency): CustomerWallesterAccount
    {
        $routingCurrency = $this->currencyResolver->resolve($currency);

        $configuredId = $this->wallesterSettings->platformMasterAccountId($routingCurrency);

        if ($configuredId !== '') {
            $account = CustomerWallesterAccount::query()
                ->where('wallester_account_id', $configuredId)
                ->first();

            if ($account instanceof CustomerWallesterAccount) {
                return $account;
            }
        }

        $platformCustomer = Customer::query()
            ->where('type', 'platform')
            ->first();

        if ($platformCustomer === null) {
            throw new RuntimeException('Platform customer not found.');
        }

        $account = $platformCustomer->platformWallesterMainAccounts()
            ->where('funding_currency', $routingCurrency)
            ->first();

        if (! $account instanceof CustomerWallesterAccount) {
            throw new RuntimeException("Platform Wallester master account not linked for {$routingCurrency}.");
        }

        return $account;
    }

    public function getPlatformFeeAccount(string $currency): CustomerWallesterAccount
    {
        $routingCurrency = $this->currencyResolver->resolve($currency);

        $configuredId = $this->wallesterSettings->platformFeeAccountId($routingCurrency);

        if ($configuredId !== '') {
            $account = CustomerWallesterAccount::query()
                ->where('wallester_account_id', $configuredId)
                ->first();

            if ($account instanceof CustomerWallesterAccount) {
                return $account;
            }
        }

        $platformCustomer = Customer::query()
            ->where('type', 'platform')
            ->get();

        foreach ($platformCustomer as $platform) {
            $account = $platform->platformWallesterFeeAccounts()
                ->where('funding_currency', $routingCurrency)
                ->first();

            if ($account instanceof CustomerWallesterAccount) {
                return $account;
            }
        }

        return $this->getPlatformMasterAccount($routingCurrency);
    }

    public function requireActiveAccount(Customer $customer, string $currency): CustomerWallesterAccount
    {
        $routingCurrency = $this->currencyResolver->resolve($currency);
        $account = $customer->wallesterAccountFor($routingCurrency);

        if (! $account instanceof CustomerWallesterAccount) {
            throw new ApiException(
                ErrorCode::FUNDING_PARTNER_ACCOUNT_NOT_FOUND,
                422,
                'Conta Wallester não encontrada para este cliente.',
            );
        }

        if (! $account->status->isOperational()) {
            throw new ApiException(
                ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
                422,
                'Conta Wallester não está activa.',
            );
        }

        return $account;
    }

    public function findAccountByExternalId(string $externalAccountId): ?CustomerWallesterAccount
    {
        return CustomerWallesterAccount::query()
            ->where('wallester_account_id', $externalAccountId)
            ->first();
    }

    public function amountToCents(float|int|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public function absoluteAmountToCents(float|int|string|null $amount): int
    {
        return abs($this->amountToCents($amount));
    }
}

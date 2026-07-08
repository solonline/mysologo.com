<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterTransferErrorClassifier;
use App\Domains\Wallet\Contracts\FundingPartnerTransferInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FundingPartnerMovementService
{
    public function __construct(
        protected FundingPartnerTransferResolver $resolver,
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected PlatformMasterCurrencyResolver $currencyResolver,
        protected PartnerTopupFundingCompleter $partnerTopupFundingCompleter,
        protected WallesterTransferErrorClassifier $transferErrorClassifier,
        protected FundingLiquidityNotifier $fundingLiquidityNotifier,
    ) {}

    public function transferBetweenCustomers(
        Customer $from,
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        if ($this->hasCompletedMovement($idempotencyKey)) {
            return;
        }

        $driver = $this->resolver->resolveForCustomer($from);
        $routingCurrency = $this->currencyResolver->resolve($currency);

        $available = $driver->getAvailableBalance($from, $routingCurrency);

        if ($available < $amountCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo insuficiente no parceiro de funding. Disponível: {$available} {$routingCurrency}.",
            );
        }

        $fromAccount = $this->resolveFundingAccount($from, $driver, $routingCurrency);
        $toAccount = $this->resolveFundingAccount($to, $driver, $routingCurrency);
        $transferGroupId = (string) Str::uuid();
        $provider = IntegrationProvider::from($driver->provider());

        DB::transaction(function () use (
            $driver,
            $from,
            $to,
            $fromAccount,
            $toAccount,
            $amountCents,
            $routingCurrency,
            $description,
            $idempotencyKey,
            $transferGroupId,
            $provider,
            $reference,
        ): void {
            $outMovement = $this->createMovement(
                provider: $provider,
                fundingAccount: $fromAccount,
                externalAccountId: $this->externalAccountId($fromAccount),
                direction: FundingPartnerMovementDirection::Out,
                type: FundingPartnerMovementType::AccountTransfer,
                amountCents: $amountCents,
                currency: $routingCurrency,
                transferGroupId: $transferGroupId,
                counterpartyExternalAccountId: $this->externalAccountId($toAccount),
                status: FundingPartnerMovementStatus::Pending,
                source: FundingPartnerMovementSource::Api,
                idempotencyKey: $idempotencyKey.':out',
                description: $description,
                reference: $reference,
            );

            $inMovement = $this->createMovement(
                provider: $provider,
                fundingAccount: $toAccount,
                externalAccountId: $this->externalAccountId($toAccount),
                direction: FundingPartnerMovementDirection::In,
                type: FundingPartnerMovementType::AccountTransfer,
                amountCents: $amountCents,
                currency: $routingCurrency,
                transferGroupId: $transferGroupId,
                counterpartyExternalAccountId: $this->externalAccountId($fromAccount),
                status: FundingPartnerMovementStatus::Pending,
                source: FundingPartnerMovementSource::Api,
                idempotencyKey: $idempotencyKey.':in',
                description: $description,
                reference: $reference,
            );

            try {
                $response = $this->executeDriverTransfer(
                    driver: $driver,
                    from: $from,
                    to: $to,
                    amountCents: $amountCents,
                    currency: $routingCurrency,
                    description: $description,
                    idempotencyKey: $idempotencyKey,
                );

                $partnerExternalId = is_array($response) ? ($response['id'] ?? null) : null;

                $this->markMovementCompleted($outMovement, is_string($partnerExternalId) ? $partnerExternalId : null);
                $this->markMovementCompleted($inMovement);
            } catch (Throwable $exception) {
                $reason = $exception->getMessage();
                $this->markMovementFailed($outMovement, $reason);
                $this->markMovementFailed($inMovement, $reason);

                throw $exception;
            }
        });
    }

    public function transferFromMasterToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        $this->retryTransferFromMasterToCustomer(
            to: $to,
            amountCents: $amountCents,
            currency: $currency,
            description: $description,
            idempotencyKey: $idempotencyKey,
            reference: $reference,
        );
    }

    public function retryTransferFromMasterToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        if ($this->hasCompletedMovement($idempotencyKey)) {
            if ($reference instanceof StripeTransaction) {
                $this->partnerTopupFundingCompleter->complete($reference);
            }

            return;
        }

        $routingCurrency = $this->currencyResolver->resolve($currency);
        $masterAccount = $this->wallesterDriver->getPlatformMasterAccount($routingCurrency);
        $toAccount = $this->wallesterDriver->requireActiveAccount($to, $routingCurrency);
        $provider = IntegrationProvider::WALLESTER;

        $pair = DB::transaction(function () use (
            $masterAccount,
            $toAccount,
            $amountCents,
            $routingCurrency,
            $description,
            $idempotencyKey,
            $provider,
            $reference,
        ) {
            return $this->resolveTransferMovementPair(
                idempotencyKey: $idempotencyKey,
                provider: $provider,
                masterAccount: $masterAccount,
                toAccount: $toAccount,
                amountCents: $amountCents,
                routingCurrency: $routingCurrency,
                description: $description,
                reference: $reference,
            );
        });

        if ($pair === null) {
            return;
        }

        [$outMovement, $inMovement] = $pair;

        try {
            $response = $this->wallesterDriver->executeTransfer(
                fromAccount: $masterAccount,
                toAccount: $toAccount,
                amountCents: $amountCents,
                description: $description,
                auditUserId: 'platform',
            );

            $partnerExternalId = is_array($response) ? ($response['id'] ?? null) : null;

            $this->markMovementCompleted($outMovement, is_string($partnerExternalId) ? $partnerExternalId : null);
            $this->markMovementCompleted($inMovement);

            if ($reference instanceof StripeTransaction) {
                $this->partnerTopupFundingCompleter->complete($reference);
            }
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->markMovementFailed($outMovement, $reason);
            $this->markMovementFailed($inMovement, $reason);
            $this->handlePartnerTopupTransferFailure($reference, $routingCurrency, $exception);

            throw $exception;
        }
    }

    public function recordCardMovement(
        CustomerWallesterAccount $account,
        FundingPartnerMovementType $type,
        FundingPartnerMovementDirection $direction,
        int $amountCents,
        string $currency,
        string $partnerExternalId,
        FundingPartnerMovementSource $source,
        ?CustomerWallesterCard $card = null,
        ?string $description = null,
        ?array $metadata = null,
    ): FundingPartnerMovement {
        $idempotencyKey = 'card:'.$partnerExternalId.':'.$direction->value;

        $existing = FundingPartnerMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof FundingPartnerMovement) {
            return $existing;
        }

        return FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => $direction,
            'type' => $type,
            'amount' => $amountCents,
            'currency' => strtoupper($currency),
            'customer_wallester_card_id' => $card?->id,
            'partner_external_id' => $partnerExternalId,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function listForFundingAccount(Model $fundingAccount, int $perPage = 15): LengthAwarePaginator
    {
        return FundingPartnerMovement::query()
            ->where('funding_account_type', $fundingAccount::class)
            ->where('funding_account_id', $fundingAccount->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function hasCompletedMovement(string $idempotencyKey): bool
    {
        return FundingPartnerMovement::query()
            ->where('idempotency_key', $idempotencyKey.':out')
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->exists();
    }

    private function executeDriverTransfer(
        FundingPartnerTransferInterface $driver,
        Customer $from,
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
    ): mixed {
        if ($driver instanceof WallesterFundingTransferDriver) {
            $fromAccount = $driver->requireActiveAccount($from, $currency);
            $toAccount = $driver->requireActiveAccount($to, $currency);

            return $driver->executeTransfer(
                fromAccount: $fromAccount,
                toAccount: $toAccount,
                amountCents: $amountCents,
                description: $description,
                auditUserId: (string) $from->id,
            );
        }

        $driver->transferBetweenCustomers(
            from: $from,
            to: $to,
            amountCents: $amountCents,
            currency: $currency,
            description: $description,
            idempotencyKey: $idempotencyKey,
        );

        return null;
    }

    private function resolveFundingAccount(
        Customer $customer,
        FundingPartnerTransferInterface $driver,
        string $currency,
    ): Model {
        if ($driver instanceof WallesterFundingTransferDriver) {
            return $driver->requireActiveAccount($customer, $currency);
        }

        throw new ApiException(
            ErrorCode::FUNDING_PARTNER_UNAVAILABLE,
            422,
            'Parceiro de funding não suportado.',
        );
    }

    private function externalAccountId(Model $account): string
    {
        if ($account instanceof CustomerWallesterAccount) {
            return $account->wallester_account_id;
        }

        throw new ApiException(
            ErrorCode::FUNDING_PARTNER_ACCOUNT_NOT_FOUND,
            422,
            'Conta de funding inválida.',
        );
    }

    private function createMovement(
        IntegrationProvider $provider,
        Model $fundingAccount,
        string $externalAccountId,
        FundingPartnerMovementDirection $direction,
        FundingPartnerMovementType $type,
        int $amountCents,
        string $currency,
        ?string $transferGroupId,
        ?string $counterpartyExternalAccountId,
        FundingPartnerMovementStatus $status,
        FundingPartnerMovementSource $source,
        string $idempotencyKey,
        ?string $description,
        ?object $reference,
    ): FundingPartnerMovement {
        return FundingPartnerMovement::query()->create([
            'provider' => $provider,
            'funding_account_type' => $fundingAccount::class,
            'funding_account_id' => $fundingAccount->id,
            'external_account_id' => $externalAccountId,
            'direction' => $direction,
            'type' => $type,
            'amount' => $amountCents,
            'currency' => $currency,
            'transfer_group_id' => $transferGroupId,
            'counterparty_external_account_id' => $counterpartyExternalAccountId,
            'status' => $status,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'reference_type' => $reference !== null ? $reference::class : null,
            'reference_id' => $reference?->id,
        ]);
    }

    private function markMovementCompleted(FundingPartnerMovement $movement, ?string $partnerExternalId = null): void
    {
        $movement->update([
            'status' => FundingPartnerMovementStatus::Completed,
            'partner_external_id' => $partnerExternalId,
        ]);
    }

    private function markMovementFailed(FundingPartnerMovement $movement, string $reason): void
    {
        $movement->update([
            'status' => FundingPartnerMovementStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * @return array{0: FundingPartnerMovement, 1: FundingPartnerMovement}|null
     */
    private function resolveTransferMovementPair(
        string $idempotencyKey,
        IntegrationProvider $provider,
        CustomerWallesterAccount $masterAccount,
        CustomerWallesterAccount $toAccount,
        int $amountCents,
        string $routingCurrency,
        string $description,
        ?object $reference,
    ): ?array {
        $outKey = $idempotencyKey.':out';
        $inKey = $idempotencyKey.':in';

        $outMovement = FundingPartnerMovement::query()->where('idempotency_key', $outKey)->first();
        $inMovement = FundingPartnerMovement::query()->where('idempotency_key', $inKey)->first();

        if ($outMovement instanceof FundingPartnerMovement
            && $outMovement->status === FundingPartnerMovementStatus::Completed) {
            return null;
        }

        if ($outMovement instanceof FundingPartnerMovement
            && $inMovement instanceof FundingPartnerMovement) {
            if ($outMovement->status === FundingPartnerMovementStatus::Failed) {
                $this->resetMovementForRetry($outMovement);
                $this->resetMovementForRetry($inMovement);
            }

            return [$outMovement->fresh(), $inMovement->fresh()];
        }

        $transferGroupId = (string) Str::uuid();

        $outMovement = $this->createMovement(
            provider: $provider,
            fundingAccount: $masterAccount,
            externalAccountId: $masterAccount->wallester_account_id,
            direction: FundingPartnerMovementDirection::Out,
            type: FundingPartnerMovementType::AccountTransfer,
            amountCents: $amountCents,
            currency: $routingCurrency,
            transferGroupId: $transferGroupId,
            counterpartyExternalAccountId: $toAccount->wallester_account_id,
            status: FundingPartnerMovementStatus::Pending,
            source: FundingPartnerMovementSource::Api,
            idempotencyKey: $outKey,
            description: $description,
            reference: $reference,
        );

        $inMovement = $this->createMovement(
            provider: $provider,
            fundingAccount: $toAccount,
            externalAccountId: $toAccount->wallester_account_id,
            direction: FundingPartnerMovementDirection::In,
            type: FundingPartnerMovementType::AccountTransfer,
            amountCents: $amountCents,
            currency: $routingCurrency,
            transferGroupId: $transferGroupId,
            counterpartyExternalAccountId: $masterAccount->wallester_account_id,
            status: FundingPartnerMovementStatus::Pending,
            source: FundingPartnerMovementSource::Api,
            idempotencyKey: $inKey,
            description: $description,
            reference: $reference,
        );

        return [$outMovement, $inMovement];
    }

    private function resetMovementForRetry(FundingPartnerMovement $movement): void
    {
        $movement->update([
            'status' => FundingPartnerMovementStatus::Pending,
            'failure_reason' => null,
            'partner_external_id' => null,
        ]);
    }

    private function handlePartnerTopupTransferFailure(
        ?object $reference,
        string $currency,
        Throwable $exception,
    ): void {
        if (! $reference instanceof StripeTransaction) {
            return;
        }

        $status = $this->transferErrorClassifier->classify($exception);

        $reference->update([
            'partner_funding_status' => $status,
            'partner_funding_failure_reason' => $exception->getMessage(),
        ]);

        if ($status === PartnerFundingStatus::AwaitingLiquidity) {
            $this->fundingLiquidityNotifier->notifyIfNeeded($reference->fresh(), $currency);
        }
    }
}

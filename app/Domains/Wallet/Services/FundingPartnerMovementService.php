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
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Events\CustomerTransferReceived;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use App\Models\RailMirrorEvent;
use App\Models\StripeTransaction;
use App\Models\WiseTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        protected LedgerService $ledger,
        protected WallesterMainAccountSyncService $mainAccountSync,
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

                $this->completeTransferPair($outMovement, $inMovement, $response);

                $this->refreshWallesterBalancesAfterTransfer(
                    driver: $driver,
                    from: $from,
                    to: $to,
                    currency: $routingCurrency,
                );

                CustomerTransferReceived::dispatch(
                    toCustomerId: (int) $to->id,
                    fromCustomerId: (int) $from->id,
                    amountCents: $amountCents,
                    currency: $routingCurrency,
                    movementId: (int) $inMovement->id,
                    description: $description,
                    sendBothSides: true,
                );
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
                fromAccount: $masterAccount,
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

            $this->completeTransferPair($outMovement, $inMovement, $response);

            $this->refreshBalancesAfterMasterTransfer(
                masterAccount: $masterAccount,
                to: $to,
                currency: $routingCurrency,
            );

            if ($reference instanceof StripeTransaction) {
                $this->partnerTopupFundingCompleter->complete($reference);
            }

            CustomerTransferReceived::dispatch(
                toCustomerId: (int) $to->id,
                fromCustomerId: (int) $masterAccount->username,
                amountCents: $amountCents,
                currency: $routingCurrency,
                movementId: (int) $inMovement->id,
                description: $description,
            );
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->markMovementFailed($outMovement, $reason);
            $this->markMovementFailed($inMovement, $reason);
            $this->handlePartnerTopupTransferFailure($reference, $routingCurrency, $exception);

            throw $exception;
        }
    }

    public function transferFromCustomerToMaster(
        Customer $from,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        $this->transferFromCustomerToPlatformAccount(
            from: $from,
            amountCents: $amountCents,
            currency: $currency,
            description: $description,
            idempotencyKey: $idempotencyKey,
            reference: $reference,
            toFeeAccount: false,
        );
    }

    public function transferFromCustomerToPlatformFees(
        Customer $from,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
        FundingPartnerMovementType $movementType = FundingPartnerMovementType::AccountTransfer,
    ): ?FundingPartnerMovement {
        return $this->transferFromCustomerToPlatformAccount(
            from: $from,
            amountCents: $amountCents,
            currency: $currency,
            description: $description,
            idempotencyKey: $idempotencyKey,
            reference: $reference,
            toFeeAccount: true,
            movementType: $movementType,
        );
    }

    public function transferFromPlatformFeesToCustomer(
        Customer $to,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): ?FundingPartnerMovement {
        if ($amountCents <= 0 || $this->hasCompletedMovement($idempotencyKey)) {
            return null;
        }

        $routingCurrency = $this->currencyResolver->resolve($currency);
        $fromAccount = $this->wallesterDriver->getPlatformFeeAccount($routingCurrency);
        $toAccount = $this->wallesterDriver->requireActiveAccount($to, $routingCurrency);
        $fromCustomer = $fromAccount->customer;

        if (! $fromCustomer instanceof Customer) {
            return null;
        }

        $available = $this->wallesterDriver->getAvailableBalance($fromCustomer, $routingCurrency);
        if ($available < $amountCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo insuficiente na conta de taxas Wallester. Disponível: {$available} {$routingCurrency}.",
            );
        }

        $provider = IntegrationProvider::WALLESTER;

        $pair = DB::transaction(function () use (
            $fromAccount,
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
                fromAccount: $fromAccount,
                toAccount: $toAccount,
                amountCents: $amountCents,
                routingCurrency: $routingCurrency,
                description: $description,
                reference: $reference,
            );
        });

        if ($pair === null) {
            return null;
        }

        [$outMovement, $inMovement] = $pair;

        try {
            $response = $this->wallesterDriver->executeTransfer(
                fromAccount: $fromAccount,
                toAccount: $toAccount,
                amountCents: $amountCents,
                description: $description,
                auditUserId: 'platform',
            );

            $this->completeTransferPair($outMovement, $inMovement, $response);

            $this->refreshBalancesAfterMasterTransfer(
                masterAccount: $fromAccount,
                to: $to,
                currency: $routingCurrency,
            );
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->markMovementFailed($outMovement, $reason);
            $this->markMovementFailed($inMovement, $reason);

            throw $exception;
        }

        return $outMovement->fresh();
    }

    public function creditFxFeeReversal(
        FundingPartnerMovement $feeMovement,
        FundingPartnerMovement $refund,
        CustomerWallesterAccount $account,
        int $amountCents,
    ): void {
        if ($amountCents <= 0) {
            return;
        }

        $query = LedgerEntry::query()
            ->where('reference_type', $refund->getMorphClass())
            ->where('reference_id', $refund->id)
            ->where('entry_type', LedgerEntryType::PAYMENT_REFUND);

        $recorded = (int) (clone $query)->sum('amount');
        $delta = $amountCents - $recorded;

        if ($delta > 0) {
            $existing = (clone $query)->first();

            if ($existing instanceof LedgerEntry) {
                DB::transaction(function () use ($existing, $delta, $amountCents, $account, $feeMovement): void {
                    $balance = CustomerBalance::query()
                        ->lockForUpdate()
                        ->where('customer_id', $account->customer_id)
                        ->where('currency', strtoupper((string) $feeMovement->currency))
                        ->first();

                    $existing->update([
                        'amount' => $amountCents,
                        'available_after' => (int) $existing->available_after + $delta,
                        'balance_after' => (int) $existing->balance_after + $delta,
                    ]);

                    $balance?->increment('wallester_allocated', $delta);
                });
            } else {
                $this->ledger->record(
                    customerId: (int) $account->customer_id,
                    currency: (string) $feeMovement->currency,
                    entryType: LedgerEntryType::PAYMENT_REFUND,
                    amount: $amountCents,
                    reference: $refund,
                    description: 'Câmbio',
                    metadata: [
                        'fx_reversal' => true,
                        'fee_movement_id' => $feeMovement->id,
                        'partner_external_id' => $refund->partner_external_id,
                    ],
                );
            }
        }

        $this->mirrorWiseFxAllocation(
            reference: $refund,
            customerId: (int) $account->customer_id,
            currency: (string) $feeMovement->currency,
            targetCents: max($amountCents, $recorded),
            direction: 'credit',
            reason: 'fx_fee_reversal',
            idempotencyPrefix: 'fx_wise_reversal:'.$refund->id,
        );
    }

    public function completeAcceptedReversal(
        FundingPartnerMovement $movement,
        CustomerWallesterAccount $account,
    ): FundingPartnerMovement {
        if ($movement->type !== FundingPartnerMovementType::CardRefund) {
            return $movement;
        }

        if ($movement->status === FundingPartnerMovementStatus::Failed) {
            $movement->update([
                'status' => FundingPartnerMovementStatus::Completed,
                'failure_reason' => null,
            ]);
            $movement = $movement->fresh();
        }

        $this->applyCardMovementLedger($movement, $account);

        return $movement;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordLocalCompletedMovement(
        CustomerWallesterAccount $account,
        FundingPartnerMovementType $type,
        FundingPartnerMovementDirection $direction,
        int $amountCents,
        string $currency,
        string $idempotencyKey,
        string $description,
        ?CustomerWallesterCard $card = null,
        ?array $metadata = null,
        ?object $reference = null,
    ): FundingPartnerMovement {
        $existing = FundingPartnerMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof FundingPartnerMovement) {
            return $existing;
        }

        $resolvedReference = $reference ?? $card;

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
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'reference_type' => $resolvedReference instanceof Model ? $resolvedReference->getMorphClass() : null,
            'reference_id' => $resolvedReference instanceof Model ? $resolvedReference->getKey() : null,
            'metadata' => $metadata,
        ]);
    }

    public function transferFromMasterToPlatformFees(
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference = null,
    ): void {
        if ($amountCents <= 0 || $this->hasCompletedMovement($idempotencyKey)) {
            return;
        }

        $routingCurrency = $this->currencyResolver->resolve($currency);
        $masterAccount = $this->wallesterDriver->getPlatformMasterAccount($routingCurrency);
        $feeAccount = $this->wallesterDriver->getPlatformFeeAccount($routingCurrency);

        if ($masterAccount->is($feeAccount)) {
            return;
        }

        $masterCustomer = $masterAccount->customer;

        if (! $masterCustomer instanceof Customer) {
            return;
        }

        $available = $this->wallesterDriver->getAvailableBalance($masterCustomer, $routingCurrency);
        if ($available < $amountCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo insuficiente na Wallester master para o lucro da plataforma. Disponível: {$available} {$routingCurrency}.",
            );
        }

        $provider = IntegrationProvider::WALLESTER;

        $pair = DB::transaction(function () use (
            $masterAccount,
            $feeAccount,
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
                fromAccount: $masterAccount,
                toAccount: $feeAccount,
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
                toAccount: $feeAccount,
                amountCents: $amountCents,
                description: $description,
                auditUserId: 'platform',
            );

            $this->completeTransferPair($outMovement, $inMovement, $response);

            $this->mainAccountSync->syncAccount($masterAccount);
            $this->mainAccountSync->syncAccount($feeAccount);
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->markMovementFailed($outMovement, $reason);
            $this->markMovementFailed($inMovement, $reason);

            throw $exception;
        }
    }

    private function transferFromCustomerToPlatformAccount(
        Customer $from,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyKey,
        ?object $reference,
        bool $toFeeAccount,
        FundingPartnerMovementType $movementType = FundingPartnerMovementType::AccountTransfer,
    ): ?FundingPartnerMovement {
        if ($this->hasCompletedMovement($idempotencyKey)) {
            return null;
        }

        $routingCurrency = $this->currencyResolver->resolve($currency);
        $fromAccount = $this->wallesterDriver->requireActiveAccount($from, $routingCurrency);
        $masterAccount = $toFeeAccount
            ? $this->wallesterDriver->getPlatformFeeAccount($routingCurrency)
            : $this->wallesterDriver->getPlatformMasterAccount($routingCurrency);
        $provider = IntegrationProvider::WALLESTER;

        $available = $this->wallesterDriver->getAvailableBalance($from, $routingCurrency);
        if ($available < $amountCents) {
            throw new ApiException(
                ErrorCode::INSUFFICIENT_BALANCE,
                422,
                "Saldo insuficiente na Wallester. Disponível: {$available} {$routingCurrency}.",
            );
        }

        $pair = DB::transaction(function () use (
            $fromAccount,
            $masterAccount,
            $amountCents,
            $routingCurrency,
            $description,
            $idempotencyKey,
            $provider,
            $reference,
            $movementType,
        ) {
            return $this->resolveTransferMovementPair(
                idempotencyKey: $idempotencyKey,
                provider: $provider,
                fromAccount: $fromAccount,
                toAccount: $masterAccount,
                amountCents: $amountCents,
                routingCurrency: $routingCurrency,
                description: $description,
                reference: $reference,
                type: $movementType,
            );
        });

        if ($pair === null) {
            return null;
        }

        [$outMovement, $inMovement] = $pair;

        try {
            $response = $this->wallesterDriver->executeTransfer(
                fromAccount: $fromAccount,
                toAccount: $masterAccount,
                amountCents: $amountCents,
                description: $description,
                auditUserId: (string) $from->id,
            );

            $this->completeTransferPair($outMovement, $inMovement, $response);

            $this->refreshBalancesAfterMasterTransfer(
                masterAccount: $masterAccount,
                to: $from,
                currency: $routingCurrency,
            );
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->markMovementFailed($outMovement, $reason);
            $this->markMovementFailed($inMovement, $reason);

            throw $exception;
        }

        return $outMovement->fresh();
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
        ?int $originalAmountCents = null,
        ?string $originalCurrency = null,
        ?int $feesAmountCents = null,
        FundingPartnerMovementStatus $status = FundingPartnerMovementStatus::Completed,
        ?string $failureReason = null,
        bool $applyLedger = true,
    ): FundingPartnerMovement {
        $idempotencyKey = 'card:'.$partnerExternalId.':'.$direction->value;

        return DB::transaction(function () use (
            $account,
            $type,
            $direction,
            $amountCents,
            $currency,
            $partnerExternalId,
            $source,
            $card,
            $description,
            $metadata,
            $idempotencyKey,
            $originalAmountCents,
            $originalCurrency,
            $feesAmountCents,
            $status,
            $failureReason,
            $applyLedger,
        ): FundingPartnerMovement {
            $existing = $this->findExistingCardMovement(
                account: $account,
                type: $type,
                direction: $direction,
                partnerExternalId: $partnerExternalId,
                metadata: $metadata,
            );

            if ($existing instanceof FundingPartnerMovement) {
                if ($existing->status === FundingPartnerMovementStatus::Failed
                    && $status === FundingPartnerMovementStatus::Completed) {
                    $existing->update([
                        'status' => FundingPartnerMovementStatus::Completed,
                        'failure_reason' => null,
                    ]);
                    $existing = $existing->fresh();
                }

                $this->attachSettlementReference($existing, $partnerExternalId, $metadata);

                if ($applyLedger) {
                    $this->applyCardMovementLedger($existing, $account);
                }

                return $existing;
            }

            $movement = FundingPartnerMovement::query()->create([
                'provider' => IntegrationProvider::WALLESTER,
                'funding_account_type' => $account::class,
                'funding_account_id' => $account->id,
                'external_account_id' => $account->wallester_account_id,
                'direction' => $direction,
                'type' => $type,
                'amount' => $amountCents,
                'currency' => strtoupper($currency),
                'original_amount' => $originalAmountCents,
                'original_currency' => $originalCurrency !== null ? strtoupper($originalCurrency) : null,
                'fees_amount' => $feesAmountCents,
                'customer_wallester_card_id' => $card?->id,
                'partner_external_id' => $partnerExternalId,
                'status' => $status,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'failure_reason' => $failureReason,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            if ($applyLedger) {
                $this->applyCardMovementLedger($movement, $account);
            }

            return $movement;
        });
    }

    public function recordCustomerFxFeeMovement(
        FundingPartnerMovement $purchase,
        CustomerWallesterAccount $account,
        int $amountCents,
        bool $applyLedger = true,
    ): FundingPartnerMovement {
        $partnerExternalId = (string) $purchase->partner_external_id;
        $idempotencyKey = 'fx-fees:'.$partnerExternalId;

        return DB::transaction(function () use (
            $purchase,
            $account,
            $amountCents,
            $idempotencyKey,
            $applyLedger,
        ): FundingPartnerMovement {
            $existing = FundingPartnerMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof FundingPartnerMovement) {
                $existing->update([
                    'amount' => $amountCents,
                    'fees_amount' => $purchase->fees_amount,
                    'platform_fees_amount' => $purchase->platform_fees_amount,
                    'status' => FundingPartnerMovementStatus::Completed,
                ]);

                if ($applyLedger) {
                    $this->applyFxFeeLedger($existing->fresh(), $account);
                }

                return $existing->fresh();
            }

            $movement = FundingPartnerMovement::query()->create([
                'provider' => IntegrationProvider::WALLESTER,
                'funding_account_type' => $account::class,
                'funding_account_id' => $account->id,
                'external_account_id' => $account->wallester_account_id,
                'direction' => FundingPartnerMovementDirection::Out,
                'type' => FundingPartnerMovementType::Fee,
                'amount' => $amountCents,
                'currency' => strtoupper((string) $purchase->currency),
                'fees_amount' => $purchase->fees_amount,
                'platform_fees_amount' => $purchase->platform_fees_amount,
                'customer_wallester_card_id' => $purchase->customer_wallester_card_id,
                'partner_external_id' => $idempotencyKey,
                'status' => FundingPartnerMovementStatus::Completed,
                'source' => $purchase->source ?? FundingPartnerMovementSource::Webhook,
                'idempotency_key' => $idempotencyKey,
                'description' => 'Câmbio',
                'reference_type' => $purchase->getMorphClass(),
                'reference_id' => $purchase->id,
            ]);

            if ($applyLedger) {
                $this->applyFxFeeLedger($movement, $account);
            }

            return $movement;
        });
    }

    public function findCardMovementByPartnerExternalId(
        CustomerWallesterAccount $account,
        string $partnerExternalId,
    ): ?FundingPartnerMovement {
        if ($partnerExternalId === '') {
            return null;
        }

        $movement = FundingPartnerMovement::query()
            ->where('funding_account_type', $account::class)
            ->where('funding_account_id', $account->id)
            ->where('partner_external_id', $partnerExternalId)
            ->first();

        return $movement instanceof FundingPartnerMovement ? $movement : null;
    }

    public function recastAsAtmWithdrawal(FundingPartnerMovement $movement): FundingPartnerMovement
    {
        if ($movement->type === FundingPartnerMovementType::CardWithdrawal) {
            return $movement;
        }

        if ($movement->type !== FundingPartnerMovementType::CardPurchase) {
            return $movement;
        }

        $movement->update(['type' => FundingPartnerMovementType::CardWithdrawal]);

        LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', LedgerEntryType::CARD_PURCHASE)
            ->update(['entry_type' => LedgerEntryType::CARD_WITHDRAWAL]);

        return $movement->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordAtmWithdrawalFee(
        ?FundingPartnerMovement $withdrawal,
        CustomerWallesterAccount $account,
        int $amountCents,
        string $currency,
        string $authorizationId,
        ?string $feePartnerExternalId = null,
        FundingPartnerMovementSource $source = FundingPartnerMovementSource::Webhook,
        ?CustomerWallesterCard $card = null,
        ?array $metadata = null,
    ): ?FundingPartnerMovement {
        if ($amountCents <= 0 || $authorizationId === '') {
            return null;
        }

        return DB::transaction(function () use (
            $withdrawal,
            $account,
            $amountCents,
            $currency,
            $authorizationId,
            $feePartnerExternalId,
            $source,
            $card,
            $metadata,
        ): FundingPartnerMovement {
            $existing = FundingPartnerMovement::query()
                ->whereIn('idempotency_key', [
                    'atm-fee:'.$authorizationId,
                    'fx-fees:'.$authorizationId,
                ])
                ->first();

            $description = 'Taxa de saque';
            $cardId = $card?->id ?? $withdrawal?->customer_wallester_card_id;
            $storedMetadata = is_array($metadata) ? $metadata : [];

            if ($feePartnerExternalId !== null && $feePartnerExternalId !== '') {
                $storedMetadata['atm_withdrawal_fee_id'] = $feePartnerExternalId;
            }

            if ($existing instanceof FundingPartnerMovement) {
                $currentMetadata = is_array($existing->metadata) ? $existing->metadata : [];

                $existing->update([
                    'type' => FundingPartnerMovementType::CardWithdrawalFee,
                    'amount' => $amountCents,
                    'currency' => strtoupper($currency),
                    'description' => $description,
                    'status' => FundingPartnerMovementStatus::Completed,
                    'customer_wallester_card_id' => $cardId,
                    'reference_type' => $withdrawal?->getMorphClass(),
                    'reference_id' => $withdrawal?->id,
                    'metadata' => array_merge($currentMetadata, $storedMetadata),
                ]);

                $movement = $existing->fresh();
            } else {
                $movement = FundingPartnerMovement::query()->create([
                    'provider' => IntegrationProvider::WALLESTER,
                    'funding_account_type' => $account::class,
                    'funding_account_id' => $account->id,
                    'external_account_id' => $account->wallester_account_id,
                    'direction' => FundingPartnerMovementDirection::Out,
                    'type' => FundingPartnerMovementType::CardWithdrawalFee,
                    'amount' => $amountCents,
                    'currency' => strtoupper($currency),
                    'customer_wallester_card_id' => $cardId,
                    'partner_external_id' => $feePartnerExternalId ?: 'atm-fee:'.$authorizationId,
                    'status' => FundingPartnerMovementStatus::Completed,
                    'source' => $source,
                    'idempotency_key' => 'atm-fee:'.$authorizationId,
                    'description' => $description,
                    'reference_type' => $withdrawal?->getMorphClass(),
                    'reference_id' => $withdrawal?->id,
                    'metadata' => $storedMetadata === [] ? null : $storedMetadata,
                ]);
            }

            $this->relabelAtmWithdrawalFeeLedger($movement);
            $this->applyAtmWithdrawalFeeLedger($movement, $account, $amountCents);

            return $movement->fresh();
        });
    }

    /**
     * Recovery: grava o par out/in de taxa Yoop→subconta já liquidado na Wallester,
     * sem nova transferência e sem ledger.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordHistoricalPlatformFeeTransfer(
        CustomerWallesterAccount $fromAccount,
        CustomerWallesterAccount $toAccount,
        int $amountCents,
        string $currency,
        string $description,
        string $idempotencyBase,
        ?string $outPartnerExternalId = null,
        ?string $inPartnerExternalId = null,
        ?array $metadata = null,
        ?object $reference = null,
    ): ?FundingPartnerMovement {
        if ($amountCents <= 0) {
            return null;
        }

        if ($this->hasCompletedMovement($idempotencyBase)) {
            return FundingPartnerMovement::query()
                ->where('idempotency_key', $idempotencyBase.':out')
                ->first();
        }

        $currency = strtoupper($currency);
        $transferGroupId = (string) Str::uuid();
        $meta = is_array($metadata) ? $metadata : [];
        $meta['recovery'] = true;
        $meta['recovery_skip_platform_fee_transfer'] = true;

        return DB::transaction(function () use (
            $fromAccount,
            $toAccount,
            $amountCents,
            $currency,
            $description,
            $idempotencyBase,
            $outPartnerExternalId,
            $inPartnerExternalId,
            $meta,
            $reference,
            $transferGroupId,
        ): FundingPartnerMovement {
            $outMovement = FundingPartnerMovement::query()->create([
                'provider' => IntegrationProvider::WALLESTER,
                'funding_account_type' => $fromAccount::class,
                'funding_account_id' => $fromAccount->id,
                'external_account_id' => $fromAccount->wallester_account_id,
                'direction' => FundingPartnerMovementDirection::Out,
                'type' => FundingPartnerMovementType::AccountTransfer,
                'amount' => $amountCents,
                'currency' => $currency,
                'transfer_group_id' => $transferGroupId,
                'counterparty_external_account_id' => $toAccount->wallester_account_id,
                'partner_external_id' => $outPartnerExternalId,
                'status' => FundingPartnerMovementStatus::Completed,
                'source' => FundingPartnerMovementSource::Sync,
                'idempotency_key' => $idempotencyBase.':out',
                'description' => $description,
                'reference_type' => $reference !== null ? $reference::class : null,
                'reference_id' => $reference?->id ?? null,
                'metadata' => $meta,
            ]);

            FundingPartnerMovement::query()->create([
                'provider' => IntegrationProvider::WALLESTER,
                'funding_account_type' => $toAccount::class,
                'funding_account_id' => $toAccount->id,
                'external_account_id' => $toAccount->wallester_account_id,
                'direction' => FundingPartnerMovementDirection::In,
                'type' => FundingPartnerMovementType::AccountTransfer,
                'amount' => $amountCents,
                'currency' => $currency,
                'transfer_group_id' => $transferGroupId,
                'counterparty_external_account_id' => $fromAccount->wallester_account_id,
                'partner_external_id' => $inPartnerExternalId ?? $outPartnerExternalId,
                'status' => FundingPartnerMovementStatus::Completed,
                'source' => FundingPartnerMovementSource::Sync,
                'idempotency_key' => $idempotencyBase.':in',
                'description' => $description,
                'reference_type' => $reference !== null ? $reference::class : null,
                'reference_id' => $reference?->id ?? null,
                'metadata' => $meta,
            ]);

            return $outMovement;
        });
    }

    /**
     * Localiza um movimento de cartão já registado (mesmo ID, autorização ligada ou settlement anexado).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function findExistingCardMovement(
        CustomerWallesterAccount $account,
        FundingPartnerMovementType $type,
        FundingPartnerMovementDirection $direction,
        string $partnerExternalId,
        ?array $metadata = null,
    ): ?FundingPartnerMovement {
        $idempotencyKey = 'card:'.$partnerExternalId.':'.$direction->value;

        $existing = FundingPartnerMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof FundingPartnerMovement) {
            return $existing;
        }

        $byPartnerId = FundingPartnerMovement::query()
            ->where('funding_account_type', $account::class)
            ->where('funding_account_id', $account->id)
            ->where('partner_external_id', $partnerExternalId)
            ->first();

        if ($byPartnerId instanceof FundingPartnerMovement) {
            return $byPartnerId->isWallesterFxFee() ? null : $byPartnerId;
        }

        return $this->findLinkedCardMovement(
            account: $account,
            type: $type,
            direction: $direction,
            partnerExternalId: $partnerExternalId,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function findLinkedCardMovement(
        CustomerWallesterAccount $account,
        FundingPartnerMovementType $type,
        FundingPartnerMovementDirection $direction,
        string $partnerExternalId,
        ?array $metadata,
    ): ?FundingPartnerMovement {
        $authorizationId = $this->extractAuthorizationId($metadata);

        // Settlement a apontar para uma authorization já ingerida.
        if ($authorizationId !== null && $authorizationId !== $partnerExternalId) {
            $byAuth = FundingPartnerMovement::query()
                ->where('funding_account_type', $account::class)
                ->where('funding_account_id', $account->id)
                ->where('type', $type)
                ->where('direction', $direction)
                ->where('partner_external_id', $authorizationId)
                ->first();

            if ($byAuth instanceof FundingPartnerMovement) {
                return $byAuth->isWallesterFxFee() ? null : $byAuth;
            }
        }

        // Authorization a chegar depois de um settlement que já referencia este ID.
        // Ou settlement da list API cujo ID já ficou em settlement_partner_external_id.
        $linked = FundingPartnerMovement::query()
            ->where('funding_account_type', $account::class)
            ->where('funding_account_id', $account->id)
            ->where('type', $type)
            ->where('direction', $direction)
            ->where(function ($query) use ($partnerExternalId): void {
                $query->where('metadata->authorization_id', $partnerExternalId)
                    ->orWhere('metadata->data->authorization_id', $partnerExternalId)
                    ->orWhere('metadata->settlement_partner_external_id', $partnerExternalId)
                    ->orWhere('metadata->settlement_metadata->id', $partnerExternalId)
                    ->orWhere('metadata->settlement_metadata->data->id', $partnerExternalId);
            })
            ->first();

        if ($linked instanceof FundingPartnerMovement && $linked->isWallesterFxFee()) {
            return null;
        }

        return $linked;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function extractAuthorizationId(?array $metadata): ?string
    {
        if (! is_array($metadata)) {
            return null;
        }

        $authorizationId = $metadata['authorization_id']
            ?? $metadata['original_authorization_id']
            ?? data_get($metadata, 'data.authorization_id')
            ?? data_get($metadata, 'data.original_authorization_id');

        if (! is_string($authorizationId) || $authorizationId === '') {
            return null;
        }

        return $authorizationId;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function attachSettlementReference(
        FundingPartnerMovement $movement,
        string $settlementExternalId,
        ?array $metadata,
    ): void {
        if ($movement->partner_external_id === $settlementExternalId) {
            return;
        }

        $current = is_array($movement->metadata) ? $movement->metadata : [];

        if (($current['settlement_partner_external_id'] ?? null) === $settlementExternalId) {
            return;
        }

        $movement->update([
            'metadata' => array_merge($current, [
                'settlement_partner_external_id' => $settlementExternalId,
                'settlement_attached_at' => now()->toIso8601String(),
                'settlement_metadata' => $metadata,
            ]),
        ]);
    }

    private function applyCardMovementLedger(
        FundingPartnerMovement $movement,
        CustomerWallesterAccount $account,
    ): void {
        $entryType = match ($movement->type) {
            FundingPartnerMovementType::CardPurchase => LedgerEntryType::CARD_PURCHASE,
            FundingPartnerMovementType::CardWithdrawal => LedgerEntryType::CARD_WITHDRAWAL,
            FundingPartnerMovementType::CardRefund => LedgerEntryType::CARD_UNLOAD,
            default => null,
        };

        if ($entryType === null || $movement->status !== FundingPartnerMovementStatus::Completed) {
            return;
        }

        $alreadyRecorded = LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', $entryType)
            ->exists();

        if (! $alreadyRecorded) {
            $this->ledger->record(
                customerId: (int) $account->customer_id,
                currency: (string) $movement->currency,
                entryType: $entryType,
                amount: (int) $movement->amount,
                reference: $movement,
                description: $movement->description,
                metadata: [
                    'partner_external_id' => $movement->partner_external_id,
                    'customer_wallester_card_id' => $movement->customer_wallester_card_id,
                ],
            );
        }

        $this->mirrorWiseAllocationForCardMovement($movement, $account, $entryType);
    }

    private function mirrorWiseAllocationForCardMovement(
        FundingPartnerMovement $movement,
        CustomerWallesterAccount $account,
        LedgerEntryType $entryType,
    ): void {
        $amountCents = (int) $movement->amount;

        if ($amountCents <= 0) {
            return;
        }

        try {
            $railMirror = app(RailMirrorService::class);
            $idempotencyKey = 'card_wise:'.$movement->id;

            if ($entryType === LedgerEntryType::CARD_PURCHASE || $entryType === LedgerEntryType::CARD_WITHDRAWAL) {
                $railMirror->debitAllocation(
                    customerId: (int) $account->customer_id,
                    currency: (string) $movement->currency,
                    amountCents: $amountCents,
                    idempotencyKey: $idempotencyKey,
                    reason: $entryType === LedgerEntryType::CARD_WITHDRAWAL ? 'card_withdrawal' : 'card_purchase',
                    reference: $movement,
                    allowNegative: true,
                );

                return;
            }

            if ($entryType === LedgerEntryType::CARD_UNLOAD) {
                $railMirror->creditAllocation(
                    customerId: (int) $account->customer_id,
                    currency: (string) $movement->currency,
                    amountCents: $amountCents,
                    idempotencyKey: $idempotencyKey,
                    reason: 'card_refund',
                    reference: $movement,
                );
            }
        } catch (Throwable $exception) {
            Log::channel('wallester')->warning('wise_allocation_card_mirror_failed', [
                'movement_id' => $movement->id,
                'customer_id' => $account->customer_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function relabelAtmWithdrawalFeeLedger(FundingPartnerMovement $movement): void
    {
        LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', LedgerEntryType::PLATFORM_FEE)
            ->update([
                'entry_type' => LedgerEntryType::CARD_WITHDRAWAL_FEE,
                'description' => 'Taxa de saque',
            ]);
    }

    private function applyAtmWithdrawalFeeLedger(
        FundingPartnerMovement $movement,
        CustomerWallesterAccount $account,
        int $amountCents,
    ): void {
        if ($movement->status !== FundingPartnerMovementStatus::Completed || $amountCents <= 0) {
            return;
        }

        $recorded = (int) LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', LedgerEntryType::CARD_WITHDRAWAL_FEE)
            ->sum('amount');

        $delta = $amountCents - $recorded;

        if ($delta > 0) {
            $this->ledger->record(
                customerId: (int) $account->customer_id,
                currency: (string) $movement->currency,
                entryType: LedgerEntryType::CARD_WITHDRAWAL_FEE,
                amount: $delta,
                reference: $movement,
                description: 'Taxa de saque',
                metadata: [
                    'partner_external_id' => $movement->partner_external_id,
                    'withdrawal_reference_id' => $movement->reference_id,
                ],
            );
        }

        $mirrored = (int) RailMirrorEvent::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('direction', 'debit')
            ->sum('amount');

        $wiseDelta = $amountCents - $mirrored;

        if ($wiseDelta <= 0 || $account->customer_id === null) {
            return;
        }

        app(RailMirrorService::class)->debitAllocation(
            customerId: (int) $account->customer_id,
            currency: (string) $movement->currency,
            amountCents: $wiseDelta,
            idempotencyKey: 'atm_wise:'.$movement->id.':'.$amountCents,
            reason: 'atm_withdrawal_fee',
            reference: $movement,
            allowNegative: true,
        );
    }

    private function applyFxFeeLedger(
        FundingPartnerMovement $movement,
        CustomerWallesterAccount $account,
    ): void {
        if ($movement->status !== FundingPartnerMovementStatus::Completed) {
            return;
        }

        $target = $this->fxFeeLedgerTarget($movement);
        $recorded = (int) LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', LedgerEntryType::PLATFORM_FEE)
            ->sum('amount');

        $delta = $target - $recorded;

        if ($delta > 0) {
            $this->ledger->record(
                customerId: (int) $account->customer_id,
                currency: (string) $movement->currency,
                entryType: LedgerEntryType::PLATFORM_FEE,
                amount: $delta,
                reference: $movement,
                description: $movement->description ?? 'Câmbio',
                metadata: [
                    'partner_external_id' => $movement->partner_external_id,
                    'purchase_reference_id' => $movement->reference_id,
                ],
            );
        }

        $this->mirrorWiseFxAllocation(
            reference: $movement,
            customerId: (int) $account->customer_id,
            currency: (string) $movement->currency,
            targetCents: $target,
            direction: 'debit',
            reason: 'fx_fee',
            idempotencyPrefix: 'fx_wise:'.$movement->id,
        );
    }

    /**
     * Espelha em wise_allocated o mesmo delta já lançado em wallester_allocated.
     * A chave inclui o alvo acumulado para um reprocessamento não debitar outra vez.
     */
    private function mirrorWiseFxAllocation(
        FundingPartnerMovement $reference,
        int $customerId,
        string $currency,
        int $targetCents,
        string $direction,
        string $reason,
        string $idempotencyPrefix,
    ): void {
        if ($targetCents <= 0 || $customerId <= 0) {
            return;
        }

        try {
            $mirrored = (int) RailMirrorEvent::query()
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->id)
                ->where('reason', $reason)
                ->where('direction', $direction)
                ->sum('amount');

            $delta = $targetCents - $mirrored;

            if ($delta <= 0) {
                return;
            }

            $railMirror = app(RailMirrorService::class);
            $idempotencyKey = $idempotencyPrefix.':'.$targetCents;

            if ($direction === 'debit') {
                $railMirror->debitAllocation(
                    customerId: $customerId,
                    currency: $currency,
                    amountCents: $delta,
                    idempotencyKey: $idempotencyKey,
                    reason: $reason,
                    reference: $reference,
                    allowNegative: true,
                );

                return;
            }

            $railMirror->creditAllocation(
                customerId: $customerId,
                currency: $currency,
                amountCents: $delta,
                idempotencyKey: $idempotencyKey,
                reason: $reason,
                reference: $reference,
            );
        } catch (Throwable $exception) {
            Log::channel('wallester')->warning('wise_allocation_fx_mirror_failed', [
                'movement_id' => $reference->id,
                'customer_id' => $customerId,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Em compras antigas o CARD_PURCHASE já inclui a taxa Wallester.
     * O ledger da row `fee` só acresce a % Yoop para não debitar o câmbio duas vezes.
     */
    private function fxFeeLedgerTarget(FundingPartnerMovement $movement): int
    {
        $fullAmount = (int) $movement->amount;
        $purchase = $movement->reference;

        if (! $purchase instanceof FundingPartnerMovement) {
            return $fullAmount;
        }

        $cardPurchaseTotal = (int) LedgerEntry::query()
            ->where('reference_type', $purchase->getMorphClass())
            ->where('reference_id', $purchase->id)
            ->where('entry_type', LedgerEntryType::CARD_PURCHASE)
            ->sum('amount');

        if ($cardPurchaseTotal >= (int) $purchase->amount + (int) $purchase->fees_amount) {
            return (int) $purchase->platform_fees_amount;
        }

        return $fullAmount;
    }

    public function listForFundingAccount(Model $fundingAccount, int $perPage = 15): LengthAwarePaginator
    {
        if ($fundingAccount instanceof CustomerWallesterAccount) {
            $this->syncPendingWiseMirrors($fundingAccount);
        }

        $page = FundingPartnerMovement::query()
            ->customerVisible()
            ->where('funding_account_type', $fundingAccount::class)
            ->where('funding_account_id', $fundingAccount->id)
            ->with(['reference', 'counterpartyWallesterAccount.customer'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        FundingPartnerMovement::hydrateWiseTransfers($page->getCollection());

        return $page;
    }

    /**
     * Envios Wise ainda sem espelho (ex. incoming_payment_waiting) passam a ter
     * a linha :out pendente, para o extrato os mostrar antes do débito Wallester.
     */
    public function syncPendingWiseMirrors(CustomerWallesterAccount $account): void
    {
        $currency = strtoupper((string) ($account->funding_currency ?: 'EUR'));

        $transfers = WiseTransfer::query()
            ->where('customer_id', $account->customer_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->get();

        foreach ($transfers as $transfer) {
            $this->recordPendingWiseMirror($account, $transfer);
        }
    }

    public function recordPendingWiseMirror(CustomerWallesterAccount $account, WiseTransfer $transfer): FundingPartnerMovement
    {
        $key = 'wise_mirror:'.$transfer->idempotency_key.':out';
        $existing = FundingPartnerMovement::query()->where('idempotency_key', $key)->first();

        if ($existing instanceof FundingPartnerMovement) {
            return $existing;
        }

        $movement = $this->createMovement(
            provider: IntegrationProvider::WALLESTER,
            fundingAccount: $account,
            externalAccountId: (string) $account->wallester_account_id,
            direction: FundingPartnerMovementDirection::Out,
            type: FundingPartnerMovementType::AccountTransfer,
            amountCents: (int) $transfer->amount_cents,
            currency: strtoupper((string) $transfer->currency),
            transferGroupId: (string) Str::uuid(),
            counterpartyExternalAccountId: null,
            status: FundingPartnerMovementStatus::Pending,
            source: FundingPartnerMovementSource::Api,
            idempotencyKey: $key,
            description: 'Wise transfer mirror #'.$transfer->wise_transfer_id,
            reference: $transfer,
        );

        if ($transfer->created_at !== null) {
            $movement->timestamps = false;
            $movement->created_at = $transfer->created_at;
            $movement->save();
        }

        return $movement;
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

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function completeTransferPair(
        FundingPartnerMovement $outMovement,
        FundingPartnerMovement $inMovement,
        mixed $response,
    ): void {
        $partnerExternalId = $this->extractPartnerExternalId($response);
        $payload = is_array($response) ? $response : null;

        $this->markMovementCompleted($outMovement, $partnerExternalId, $payload);
        $this->markMovementCompleted($inMovement, $partnerExternalId, $payload);

        $this->applyFxPlatformFeeAccountLedger($outMovement->fresh());
        $this->applyFxPlatformFeeAccountLedger($inMovement->fresh());
    }

    /**
     * Credita/debita o ledger do dono da subconta (fee account) nas transferências Câmbio Yoop.
     * Idempotente por reference + entry_type.
     */
    public function applyFxPlatformFeeAccountLedger(?FundingPartnerMovement $movement): ?LedgerEntry
    {
        if (! $movement instanceof FundingPartnerMovement) {
            return null;
        }

        if ($movement->status !== FundingPartnerMovementStatus::Completed) {
            return null;
        }

        if (! $this->isFxPlatformFeeAccountMovement($movement)) {
            return null;
        }

        $account = $movement->fundingAccount;

        if (! $account instanceof CustomerWallesterAccount || $account->customer_id === null) {
            return null;
        }

        $direction = $movement->direction;
        $entryType = $direction === FundingPartnerMovementDirection::In
            ? LedgerEntryType::PLATFORM_FEE_INCOME
            : LedgerEntryType::PLATFORM_FEE;

        $already = (int) LedgerEntry::query()
            ->where('reference_type', $movement->getMorphClass())
            ->where('reference_id', $movement->id)
            ->where('entry_type', $entryType)
            ->sum('amount');

        $amount = (int) $movement->amount;

        if ($amount <= 0 || $already >= $amount) {
            return null;
        }

        return $this->ledger->record(
            customerId: (int) $account->customer_id,
            currency: strtoupper((string) ($movement->currency ?: 'EUR')),
            entryType: $entryType,
            amount: $amount - $already,
            reference: $movement,
            description: $movement->description ?: ($direction === FundingPartnerMovementDirection::In
                ? 'Câmbio Yoop'
                : 'Câmbio Yoop (reversão)'),
            metadata: [
                'source' => 'fx_platform_fee_account_ledger',
                'idempotency_key' => $movement->idempotency_key,
                'partner_external_id' => $movement->partner_external_id,
            ],
        );
    }

    private function isFxPlatformFeeAccountMovement(FundingPartnerMovement $movement): bool
    {
        $key = (string) $movement->idempotency_key;

        if ($key === '' || (
            ! str_starts_with($key, 'fx-platform:')
            && ! str_starts_with($key, 'fx-platform-reversal:')
            && ! str_starts_with($key, 'fx-platform-recovery:')
        )) {
            return false;
        }

        $account = $movement->fundingAccount;

        if (! $account instanceof CustomerWallesterAccount) {
            return false;
        }

        $currency = strtoupper((string) ($movement->currency ?: $account->funding_currency ?: 'EUR'));

        try {
            $feeAccount = $this->wallesterDriver->getPlatformFeeAccount($currency);
        } catch (Throwable) {
            return false;
        }

        return $account->is($feeAccount);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function markMovementCompleted(
        FundingPartnerMovement $movement,
        ?string $partnerExternalId = null,
        ?array $response = null,
    ): void {
        $metadata = is_array($movement->metadata) ? $movement->metadata : [];

        if (is_array($response)) {
            $metadata['wallester_payment'] = $response;
        }

        $updates = [
            'status' => FundingPartnerMovementStatus::Completed,
            'partner_external_id' => $partnerExternalId,
        ];

        if ($metadata !== []) {
            $updates['metadata'] = $metadata;
        }

        $movement->update($updates);
    }

    private function extractPartnerExternalId(mixed $response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        foreach (['id', 'payment_id'] as $key) {
            $value = $response[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $nested = $response['payment']['id'] ?? null;

        return is_string($nested) && $nested !== '' ? $nested : null;
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
        CustomerWallesterAccount $fromAccount,
        CustomerWallesterAccount $toAccount,
        int $amountCents,
        string $routingCurrency,
        string $description,
        ?object $reference,
        FundingPartnerMovementType $type = FundingPartnerMovementType::AccountTransfer,
    ): ?array {
        $outKey = $idempotencyKey.':out';
        $inKey = $idempotencyKey.':in';

        $outMovement = FundingPartnerMovement::query()->where('idempotency_key', $outKey)->first();
        $inMovement = FundingPartnerMovement::query()->where('idempotency_key', $inKey)->first();

        if ($outMovement instanceof FundingPartnerMovement
            && $outMovement->status === FundingPartnerMovementStatus::Completed) {
            return null;
        }

        if ($inMovement instanceof FundingPartnerMovement
            && $inMovement->status === FundingPartnerMovementStatus::Completed
            && ! $outMovement instanceof FundingPartnerMovement) {
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

        $transferGroupId = (string) ($outMovement?->transfer_group_id ?: $inMovement?->transfer_group_id ?: Str::uuid());

        if ($outMovement instanceof FundingPartnerMovement && (string) $outMovement->transfer_group_id === '') {
            $outMovement->update(['transfer_group_id' => $transferGroupId]);
        }

        if (! $outMovement instanceof FundingPartnerMovement) {
            $outMovement = $this->createMovement(
                provider: $provider,
                fundingAccount: $fromAccount,
                externalAccountId: $fromAccount->wallester_account_id,
                direction: FundingPartnerMovementDirection::Out,
                type: $type,
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
        }

        if (! $inMovement instanceof FundingPartnerMovement) {
            $inMovement = $this->createMovement(
                provider: $provider,
                fundingAccount: $toAccount,
                externalAccountId: $toAccount->wallester_account_id,
                direction: FundingPartnerMovementDirection::In,
                type: $type,
                amountCents: $amountCents,
                currency: $routingCurrency,
                transferGroupId: $transferGroupId,
                counterpartyExternalAccountId: $fromAccount->wallester_account_id,
                status: FundingPartnerMovementStatus::Pending,
                source: FundingPartnerMovementSource::Api,
                idempotencyKey: $inKey,
                description: $description,
                reference: $reference,
            );
        }

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

    private function refreshWallesterBalancesAfterTransfer(
        FundingPartnerTransferInterface $driver,
        Customer $from,
        Customer $to,
        string $currency,
    ): void {
        if (! $driver instanceof WallesterFundingTransferDriver) {
            return;
        }

        $driver->getAvailableBalance($from, $currency);
        $driver->getAvailableBalance($to, $currency);
    }

    private function refreshBalancesAfterMasterTransfer(
        CustomerWallesterAccount $masterAccount,
        Customer $to,
        string $currency,
    ): void {
        $this->mainAccountSync->syncAccount($masterAccount);
        $this->wallesterDriver->getAvailableBalance($to, $currency);
    }
}

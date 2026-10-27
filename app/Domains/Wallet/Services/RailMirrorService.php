<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Jobs\MirrorWallesterDebitAfterWiseTransferJob;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\RailMirrorEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RailMirrorService
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    public function creditAllocation(
        int $customerId,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        string $reason,
        ?Model $reference = null,
        array $metadata = [],
    ): bool {
        return $this->applyAllocationDelta(
            customerId: $customerId,
            currency: $currency,
            amountCents: $amountCents,
            delta: $amountCents,
            direction: 'credit',
            idempotencyKey: $idempotencyKey,
            reason: $reason,
            reference: $reference,
            metadata: $metadata,
        );
    }

    public function debitAllocation(
        int $customerId,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        string $reason,
        ?Model $reference = null,
        array $metadata = [],
        bool $allowNegative = false,
    ): bool {
        return $this->applyAllocationDelta(
            customerId: $customerId,
            currency: $currency,
            amountCents: $amountCents,
            delta: -$amountCents,
            direction: 'debit',
            idempotencyKey: $idempotencyKey,
            reason: $reason,
            reference: $reference,
            metadata: $metadata,
            allowNegative: $allowNegative,
        );
    }

    public function reallocateBetweenCustomers(
        int $fromCustomerId,
        int $toCustomerId,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        ?Model $fromReference = null,
        ?Model $toReference = null,
    ): void {
        if ($amountCents <= 0) {
            return;
        }

        $this->debitAllocation(
            customerId: $fromCustomerId,
            currency: $currency,
            amountCents: $amountCents,
            idempotencyKey: $idempotencyKey.':from',
            reason: 'p2p_send',
            reference: $fromReference,
            allowNegative: true,
        );

        $this->creditAllocation(
            customerId: $toCustomerId,
            currency: $currency,
            amountCents: $amountCents,
            idempotencyKey: $idempotencyKey.':to',
            reason: 'p2p_receive',
            reference: $toReference,
        );
    }

    /**
     * Lock spendable entitlement before calling the Wise API.
     * Debits ledger available and wise_allocated atomically.
     */
    public function reserveForWiseTransfer(
        Customer $customer,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        ?Model $reference = null,
        ?int $feeCents = null,
        ?string $feeCurrency = null,
    ): void {
        if ($amountCents <= 0) {
            throw new ApiException(ErrorCode::INSUFFICIENT_BALANCE, 422, 'Invalid Wise transfer amount.');
        }

        $currency = strtoupper($currency);

        $reserve = function () use ($customer, $currency, $amountCents, $idempotencyKey, $reference, $feeCents, $feeCurrency): void {
            if (RailMirrorEvent::query()->where('idempotency_key', $idempotencyKey.':reserve')->exists()) {
                return;
            }

            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id, 'currency' => $currency],
                ['wallester_allocated' => 0, 'pending' => 0, 'wise_allocated' => 0],
            );

            if ((int) $balance->wallester_allocated < $amountCents) {
                throw new ApiException(
                    ErrorCode::INSUFFICIENT_BALANCE,
                    422,
                    'Saldo insuficiente para transferência Wise.',
                );
            }

            $this->ledgerService->record(
                customerId: (int) $customer->id,
                currency: $currency,
                entryType: LedgerEntryType::WISE_TRANSFER,
                amount: $amountCents,
                reference: $reference,
                description: 'Wise transfer reservation '.$idempotencyKey,
                metadata: array_filter([
                    'rail_mirror' => 'wise_transfer_reserve',
                    'idempotency_key' => $idempotencyKey,
                    'wise_fee_cents' => $feeCents,
                    'wise_fee_currency' => $feeCurrency !== null ? strtoupper($feeCurrency) : null,
                ], fn (mixed $value): bool => $value !== null),
            );

            $this->debitAllocation(
                customerId: (int) $customer->id,
                currency: $currency,
                amountCents: $amountCents,
                idempotencyKey: $idempotencyKey.':reserve',
                reason: 'wise_transfer_reserve',
                reference: $reference,
            );
        };

        if (DB::transactionLevel() > 0) {
            $reserve();

            return;
        }

        DB::transaction($reserve);
    }

    public function releaseWiseTransferReservation(
        Customer $customer,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        ?Model $reference = null,
    ): void {
        if ($amountCents <= 0) {
            return;
        }

        $currency = strtoupper($currency);
        $releaseKey = $idempotencyKey.':release';

        $release = function () use ($customer, $currency, $amountCents, $idempotencyKey, $releaseKey, $reference): void {
            if (RailMirrorEvent::query()->where('idempotency_key', $releaseKey)->exists()) {
                return;
            }

            if (! RailMirrorEvent::query()->where('idempotency_key', $idempotencyKey.':reserve')->exists()) {
                return;
            }

            $this->ledgerService->record(
                customerId: (int) $customer->id,
                currency: $currency,
                entryType: LedgerEntryType::MANUAL_CREDIT,
                amount: $amountCents,
                reference: $reference,
                description: 'Wise transfer reservation release '.$idempotencyKey,
                metadata: ['rail_mirror' => 'wise_transfer_release', 'idempotency_key' => $idempotencyKey],
            );

            $this->creditAllocation(
                customerId: (int) $customer->id,
                currency: $currency,
                amountCents: $amountCents,
                idempotencyKey: $releaseKey,
                reason: 'wise_transfer_release',
                reference: $reference,
            );
        };

        if (DB::transactionLevel() > 0) {
            $release();

            return;
        }

        DB::transaction($release);
    }

    public function dispatchWallesterMirrorDebit(
        Customer $customer,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        string $description,
    ): void {
        MirrorWallesterDebitAfterWiseTransferJob::dispatch(
            customerId: (int) $customer->id,
            amountCents: $amountCents,
            currency: strtoupper($currency),
            idempotencyKey: $idempotencyKey,
            description: $description,
        );
    }

    public function pullWallesterMirrorToMaster(
        Customer $customer,
        string $currency,
        int $amountCents,
        string $idempotencyKey,
        string $description,
    ): void {
        if ($amountCents <= 0) {
            return;
        }

        try {
            app(FundingPartnerMovementService::class)->transferFromCustomerToMaster(
                from: $customer,
                amountCents: $amountCents,
                currency: strtoupper($currency),
                description: $description,
                idempotencyKey: 'wise_mirror:'.$idempotencyKey,
            );
        } catch (Throwable $exception) {
            Log::channel('wise')->error('wallester_mirror_debit_after_wise_failed', [
                'customer_id' => $customer->id,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyAllocationDelta(
        int $customerId,
        string $currency,
        int $amountCents,
        int $delta,
        string $direction,
        string $idempotencyKey,
        string $reason,
        ?Model $reference,
        array $metadata,
        bool $allowNegative = false,
    ): bool {
        if ($amountCents <= 0) {
            return false;
        }

        $currency = strtoupper($currency);

        $apply = function () use (
            $customerId,
            $currency,
            $amountCents,
            $delta,
            $direction,
            $idempotencyKey,
            $reason,
            $reference,
            $metadata,
            $allowNegative,
        ): bool {
            if (RailMirrorEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return false;
            }

            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customerId, 'currency' => $currency],
                ['wallester_allocated' => 0, 'pending' => 0, 'wise_allocated' => 0],
            );

            $before = (int) $balance->wise_allocated;
            $after = $before + $delta;

            if (! $allowNegative && $delta < 0 && $after < 0) {
                throw new RuntimeException(
                    "Cannot debit {$amountCents} from wise_allocated; only {$before} available for customer {$customerId}."
                );
            }

            $balance->update(['wise_allocated' => $after]);

            RailMirrorEvent::query()->create([
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $customerId,
                'currency' => $currency,
                'direction' => $direction,
                'amount' => $amountCents,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'metadata' => array_merge($metadata, [
                    'wise_allocated_before' => $before,
                    'wise_allocated_after' => $after,
                ]),
            ]);

            return true;
        };

        if (DB::transactionLevel() > 0) {
            return $apply();
        }

        return DB::transaction($apply);
    }
}

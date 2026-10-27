<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function record(
        int $customerId,
        string $currency,
        LedgerEntryType $entryType,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        $direction = $entryType->direction();
        $availableDelta = $direction === TransactionDirection::Credit ? $amount : -$amount;

        return $this->post(
            customerId: $customerId,
            currency: $currency,
            entryType: $entryType,
            direction: $direction,
            amount: $amount,
            availableDelta: $availableDelta,
            pendingDelta: 0,
            reference: $reference,
            description: $description,
            metadata: $metadata ?: null,
        );
    }

    public function recordPendingCredit(
        int $customerId,
        string $currency,
        LedgerEntryType $entryType,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        return $this->post(
            customerId: $customerId,
            currency: $currency,
            entryType: $entryType,
            direction: TransactionDirection::Credit,
            amount: $amount,
            availableDelta: 0,
            pendingDelta: $amount,
            reference: $reference,
            description: $description,
            metadata: array_merge($metadata, ['bucket' => 'pending']),
        );
    }

    public function releasePendingToAvailable(
        int $customerId,
        string $currency,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        return $this->post(
            customerId: $customerId,
            currency: $currency,
            entryType: LedgerEntryType::PARTNER_FUNDING_RELEASED,
            direction: TransactionDirection::Credit,
            amount: $amount,
            availableDelta: $amount,
            pendingDelta: -$amount,
            reference: $reference,
            description: $description,
            metadata: $metadata ?: null,
            requirePending: $amount,
        );
    }

    public function reversePendingCredit(
        int $customerId,
        string $currency,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        return $this->post(
            customerId: $customerId,
            currency: $currency,
            entryType: LedgerEntryType::MANUAL_DEBIT,
            direction: TransactionDirection::Debit,
            amount: $amount,
            availableDelta: 0,
            pendingDelta: -$amount,
            reference: $reference,
            description: $description,
            metadata: array_merge($metadata, ['bucket' => 'pending', 'purge' => true]),
            requirePending: $amount,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function post(
        int $customerId,
        string $currency,
        LedgerEntryType $entryType,
        TransactionDirection $direction,
        int $amount,
        int $availableDelta,
        int $pendingDelta,
        ?Model $reference,
        ?string $description,
        ?array $metadata,
        ?int $requirePending = null,
    ): LedgerEntry {
        $currency = strtoupper($currency);

        return DB::transaction(function () use (
            $customerId,
            $currency,
            $entryType,
            $direction,
            $amount,
            $availableDelta,
            $pendingDelta,
            $reference,
            $description,
            $metadata,
            $requirePending,
        ) {
            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customerId, 'currency' => $currency],
                ['wallester_allocated' => 0, 'pending' => 0, 'wise_allocated' => 0],
            );

            $availableBefore = (int) $balance->wallester_allocated;
            $pendingBefore = (int) $balance->pending;

            if ($requirePending !== null && $pendingBefore < $requirePending) {
                $action = $availableDelta > 0 ? 'release' : 'reverse';

                throw new \RuntimeException(
                    "Cannot {$action} {$requirePending} from pending; only {$pendingBefore} available."
                );
            }

            $availableAfter = $availableBefore + $availableDelta;
            $pendingAfter = $pendingBefore + $pendingDelta;

            $entry = LedgerEntry::create([
                'customer_id' => $customerId,
                'currency' => $currency,
                'direction' => $direction,
                'amount' => $amount,
                'available_before' => $availableBefore,
                'available_after' => $availableAfter,
                'pending_before' => $pendingBefore,
                'pending_after' => $pendingAfter,
                'balance_after' => $availableAfter,
                'entry_type' => $entryType,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $balance->update([
                'wallester_allocated' => $availableAfter,
                'pending' => $pendingAfter,
            ]);

            return $entry;
        });
    }

    public function hasPendingCreditReversal(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::MANUAL_DEBIT)
            ->where('description', 'Stripe pending payment purge #'.$transaction->id)
            ->exists();
    }

    public function hasPlatformFeeReversal(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::MANUAL_DEBIT)
            ->where('description', 'Platform fee reversal #'.$transaction->id)
            ->exists();
    }

    public function pendingHeldFor(Model $reference): int
    {
        return (int) LedgerEntry::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->selectRaw('COALESCE(SUM(pending_after - pending_before), 0) as held')
            ->value('held');
    }

    public function hasReleasedPartnerFunding(StripeTransaction $transaction): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $transaction->getMorphClass())
            ->where('reference_id', $transaction->id)
            ->where('entry_type', LedgerEntryType::PARTNER_FUNDING_RELEASED)
            ->exists();
    }

    public function recalculateBalance(int $customerId, string $currency): int
    {
        $currency = strtoupper($currency);

        $credits = $this->availableLedgerQuery($customerId, $currency)
            ->where('direction', TransactionDirection::Credit)
            ->sum('amount');

        $debits = $this->availableLedgerQuery($customerId, $currency)
            ->where('direction', TransactionDirection::Debit)
            ->sum('amount');

        return (int) ($credits - $debits);
    }

    /**
     * Entries that affect customer_balances.wallester_allocated.
     * Pending-bucket credits/debits only move pending and must be excluded
     * (partner funding later posts a separate PARTNER_FUNDING_RELEASED credit).
     */
    private function availableLedgerQuery(int $customerId, string $currency): Builder
    {
        return LedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->where(function ($query): void {
                $query->whereNull('metadata->bucket')
                    ->orWhere('metadata->bucket', '!=', 'pending');
            });
    }

    public function isBalanceConsistent(int $customerId, string $currency): bool
    {
        $currency = strtoupper($currency);

        $fromLedger = $this->recalculateBalance($customerId, $currency);
        $fromBalance = CustomerBalance::where('customer_id', $customerId)
            ->where('currency', $currency)
            ->value('wallester_allocated') ?? 0;

        return $fromLedger === (int) $fromBalance;
    }
}

<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\Transaction;
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
        $currency = strtoupper($currency);
        $direction = $entryType->direction();
        $delta = $direction === TransactionDirection::Credit ? $amount : -$amount;

        return DB::transaction(function () use (
            $customerId, $currency, $entryType, $direction, $amount, $delta,
            $reference, $description, $metadata
        ) {
            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customerId, 'currency' => $currency],
                ['available' => 0, 'pending' => 0],
            );

            $newBalance = $balance->available + $delta;

            $entry = LedgerEntry::create([
                'customer_id' => $customerId,
                'currency' => $currency,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'entry_type' => $entryType,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]);

            $balance->update(['available' => $newBalance]);

            return $entry;
        });
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
        $currency = strtoupper($currency);

        return DB::transaction(function () use (
            $customerId, $currency, $entryType, $amount, $reference, $description, $metadata
        ) {
            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customerId, 'currency' => $currency],
                ['available' => 0, 'pending' => 0],
            );

            $newPending = (int) $balance->pending + $amount;

            $entry = LedgerEntry::create([
                'customer_id' => $customerId,
                'currency' => $currency,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'balance_after' => (int) $balance->available,
                'entry_type' => $entryType,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => array_merge($metadata, ['bucket' => 'pending']),
            ]);

            $balance->update(['pending' => $newPending]);

            return $entry;
        });
    }

    public function releasePendingToAvailable(
        int $customerId,
        string $currency,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        array $metadata = [],
    ): LedgerEntry {
        $currency = strtoupper($currency);

        return DB::transaction(function () use (
            $customerId, $currency, $amount, $reference, $description, $metadata
        ) {
            $balance = CustomerBalance::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customerId, 'currency' => $currency],
                ['available' => 0, 'pending' => 0],
            );

            if ((int) $balance->pending < $amount) {
                throw new \RuntimeException(
                    "Cannot release {$amount} from pending; only {$balance->pending} available."
                );
            }

            $newPending = (int) $balance->pending - $amount;
            $newAvailable = (int) $balance->available + $amount;

            $entry = LedgerEntry::create([
                'customer_id' => $customerId,
                'currency' => $currency,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'balance_after' => $newAvailable,
                'entry_type' => LedgerEntryType::PARTNER_FUNDING_RELEASED,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]);

            $balance->update([
                'pending' => $newPending,
                'available' => $newAvailable,
            ]);

            return $entry;
        });
    }

    public function hasReleasedPartnerFunding(Transaction $transaction): bool
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

        $credits = LedgerEntry::where('customer_id', $customerId)
            ->where('currency', $currency)
            ->where('direction', TransactionDirection::Credit)
            ->sum('amount');

        $debits = LedgerEntry::where('customer_id', $customerId)
            ->where('currency', $currency)
            ->where('direction', TransactionDirection::Debit)
            ->sum('amount');

        return (int) ($credits - $debits);
    }

    public function isBalanceConsistent(int $customerId, string $currency): bool
    {
        $currency = strtoupper($currency);

        $fromLedger = $this->recalculateBalance($customerId, $currency);
        $fromBalance = CustomerBalance::where('customer_id', $customerId)
            ->where('currency', $currency)
            ->value('available') ?? 0;

        return $fromLedger === (int) $fromBalance;
    }
}

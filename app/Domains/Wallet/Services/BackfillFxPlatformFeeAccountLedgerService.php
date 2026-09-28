<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use Throwable;

class BackfillFxPlatformFeeAccountLedgerService
{
    public function __construct(
        protected FundingPartnerMovementService $movements,
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected WallesterMainAccountSyncService $mainAccountSync,
        protected LedgerService $ledger,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     fee_account_id: string|null,
     *     credited: int,
     *     debited: int,
     *     skipped: int,
     *     credited_cents: int,
     *     debited_cents: int,
     *     realigned: bool,
     *     from_cents: int,
     *     to_cents: int,
     *     delta_cents: int,
     * }
     */
    public function execute(string $currency = 'EUR', bool $dryRun = false): array
    {
        $currency = strtoupper($currency);

        $feeAccount = $this->wallesterDriver->getPlatformFeeAccount($currency);

        $movements = FundingPartnerMovement::query()
            ->where('funding_account_type', $feeAccount::class)
            ->where('funding_account_id', $feeAccount->id)
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'fx-platform:%')
                    ->orWhere('idempotency_key', 'like', 'fx-platform-reversal:%')
                    ->orWhere('idempotency_key', 'like', 'fx-platform-recovery:%');
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $credited = 0;
        $debited = 0;
        $skipped = 0;
        $creditedCents = 0;
        $debitedCents = 0;

        foreach ($movements as $movement) {
            if ($dryRun) {
                $would = $this->pendingLedgerAction($movement);

                if ($would === null) {
                    $skipped++;
                } elseif ($would === 'credit') {
                    $credited++;
                    $creditedCents += (int) $movement->amount;
                } else {
                    $debited++;
                    $debitedCents += (int) $movement->amount;
                }

                continue;
            }

            $entry = $this->movements->applyFxPlatformFeeAccountLedger($movement);

            if ($entry === null) {
                $skipped++;

                continue;
            }

            if ($entry->entry_type === LedgerEntryType::PLATFORM_FEE_INCOME) {
                $credited++;
                $creditedCents += (int) $entry->amount;
            } else {
                $debited++;
                $debitedCents += (int) $entry->amount;
            }
        }

        $realign = $this->realignFeeAccountBalance(
            feeAccount: $feeAccount,
            currency: $currency,
            dryRun: $dryRun,
            projectedDeltaCents: $dryRun ? ($creditedCents - $debitedCents) : 0,
        );

        return [
            'currency' => $currency,
            'fee_account_id' => $feeAccount->wallester_account_id,
            'credited' => $credited,
            'debited' => $debited,
            'skipped' => $skipped,
            'credited_cents' => $creditedCents,
            'debited_cents' => $debitedCents,
            'realigned' => $realign['applied'],
            'from_cents' => $realign['from_cents'],
            'to_cents' => $realign['to_cents'],
            'delta_cents' => $realign['delta_cents'],
        ];
    }

    private function pendingLedgerAction(FundingPartnerMovement $movement): ?string
    {
        if ((int) $movement->amount <= 0) {
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

        if ($already >= (int) $movement->amount) {
            return null;
        }

        return $entryType === LedgerEntryType::PLATFORM_FEE_INCOME ? 'credit' : 'debit';
    }

    private function realignFeeAccountBalance(
        CustomerWallesterAccount $feeAccount,
        string $currency,
        bool $dryRun,
        int $projectedDeltaCents = 0,
    ): array {
        try {
            $this->mainAccountSync->syncAccount($feeAccount);
            $feeAccount->refresh();
        } catch (Throwable) {
            // keep local available_amount
        }

        $target = (int) ($feeAccount->available_amount ?? 0);
        $customer = $feeAccount->customer;

        if (! $customer instanceof Customer) {
            return ['applied' => false, 'from_cents' => 0, 'to_cents' => $target, 'delta_cents' => 0];
        }

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', $currency)
            ->first();

        $from = (int) ($balance?->available ?? 0);
        $fromAfterBackfill = $from + $projectedDeltaCents;
        $delta = $target - $fromAfterBackfill;

        if ($delta === 0 || $dryRun) {
            return [
                'applied' => false,
                'from_cents' => $fromAfterBackfill,
                'to_cents' => $target,
                'delta_cents' => $delta,
            ];
        }

        $this->ledger->record(
            customerId: (int) $customer->id,
            currency: $currency,
            entryType: $delta > 0 ? LedgerEntryType::MANUAL_CREDIT : LedgerEntryType::MANUAL_DEBIT,
            amount: abs($delta),
            description: 'Compensação backfill ledger Câmbio subconta',
            metadata: [
                'source' => 'fx_platform_fee_account_ledger_backfill',
                'wallester_account_id' => $feeAccount->wallester_account_id,
                'from_cents' => $fromAfterBackfill,
                'to_cents' => $target,
            ],
        );

        return [
            'applied' => true,
            'from_cents' => $fromAfterBackfill,
            'to_cents' => $target,
            'delta_cents' => $delta,
        ];
    }
}

<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Models\CustomerWallesterAccount;
use App\Models\FundingPartnerMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReassignPlatformFeesToSubMasterService
{
    public function __construct(
        protected WallesterFundingTransferDriver $wallesterDriver,
        protected WallesterMainAccountSyncService $mainAccountSync,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     transferred: bool,
     *     amount_cents: int,
     *     currency: string,
     *     rows: int,
     *     master_account_id: string,
     *     fee_account_id: string
     * }
     */
    public function execute(bool $dryRun = false): array
    {
        $master = $this->wallesterDriver->getPlatformMasterAccount('EUR');
        $fee = $this->wallesterDriver->getPlatformFeeAccount('EUR');

        if ($master->is($fee)) {
            throw new RuntimeException('Sub Conta Master não está configurada (fee account = master).');
        }

        $ins = $this->profitInMovementsOn($master);

        $amountCents = (int) $ins->sum('amount');

        $summary = [
            'dry_run' => $dryRun,
            'transferred' => false,
            'amount_cents' => $amountCents,
            'currency' => 'EUR',
            'rows' => $ins->count(),
            'master_account_id' => $master->wallester_account_id,
            'fee_account_id' => $fee->wallester_account_id,
        ];

        if ($amountCents <= 0 || $ins->isEmpty()) {
            return $summary;
        }

        if ($dryRun) {
            return $summary;
        }

        $this->wallesterDriver->executeTransfer(
            fromAccount: $master,
            toAccount: $fee,
            amountCents: $amountCents,
            description: 'Reclassificação lucro plataforma',
            auditUserId: 'platform',
        );

        $summary['transferred'] = true;

        DB::transaction(function () use ($ins, $master, $fee): void {
            $inIds = $ins->pluck('id');

            FundingPartnerMovement::query()
                ->whereIn('id', $inIds)
                ->update([
                    'funding_account_id' => $fee->id,
                    'external_account_id' => $fee->wallester_account_id,
                ]);

            $outKeys = $ins
                ->map(fn (FundingPartnerMovement $movement): string => str_replace(':in', ':out', (string) $movement->idempotency_key))
                ->filter()
                ->values();

            FundingPartnerMovement::query()
                ->whereIn('idempotency_key', $outKeys)
                ->where('direction', FundingPartnerMovementDirection::Out)
                ->where('counterparty_external_account_id', $master->wallester_account_id)
                ->update([
                    'counterparty_external_account_id' => $fee->wallester_account_id,
                ]);
        });

        $this->syncBalances($master, $fee, $amountCents);

        return $summary;
    }

    /**
     * @return Collection<int, FundingPartnerMovement>
     */
    private function profitInMovementsOn(CustomerWallesterAccount $master): Collection
    {
        return FundingPartnerMovement::query()
            ->where('funding_account_type', CustomerWallesterAccount::class)
            ->where('funding_account_id', $master->id)
            ->where('direction', FundingPartnerMovementDirection::In)
            ->where('status', FundingPartnerMovementStatus::Completed)
            ->where(function ($query): void {
                $query->where('idempotency_key', 'like', 'fx-platform:%:in')
                    ->orWhere('idempotency_key', 'like', 'stripe-platform-fee:%:in');
            })
            ->get();
    }

    private function syncBalances(
        CustomerWallesterAccount $master,
        CustomerWallesterAccount $fee,
        int $amountCents,
    ): void {
        try {
            $this->mainAccountSync->syncAccount($master);
            $this->mainAccountSync->syncAccount($fee);
        } catch (\Throwable) {
            $master->update([
                'available_amount' => max(0, (int) $master->available_amount - $amountCents),
                'balance' => max(0, (int) $master->balance - $amountCents),
            ]);
            $fee->update([
                'available_amount' => (int) $fee->available_amount + $amountCents,
                'balance' => (int) $fee->balance + $amountCents,
            ]);
        }
    }
}

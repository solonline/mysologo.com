<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wise\Enums\WiseTransferStatus;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use App\Models\StripeTransaction;
use App\Models\WiseTransfer;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlignCustomerWalletsToWallesterService
{
    public const DESCRIPTION = 'Alinhamento ao saldo Wallester';

    public const PENDING_DESCRIPTION = 'Alinhamento ao saldo Wallester: pendente sem payout';

    public function __construct(
        protected WallesterMainAccountSyncService $sync,
        protected LedgerService $ledger,
    ) {}

    /**
     * @return list<array{
     *     customer_id: int,
     *     customer_name: string,
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int|null,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool,
     *     pending_reversed_cents: int,
     *     transfers_closed: int
     * }>
     */
    public function execute(bool $dryRun = false): array
    {
        return $this->run($dryRun, mirror: false);
    }

    /**
     * @return list<array{
     *     customer_id: int,
     *     customer_name: string,
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int|null,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool,
     *     pending_reversed_cents: int,
     *     transfers_closed: int
     * }>
     */
    public function mirror(bool $dryRun = false): array
    {
        return $this->run($dryRun, mirror: true);
    }

    /**
     * @return list<array{
     *     customer_id: int,
     *     customer_name: string,
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int|null,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool,
     *     pending_reversed_cents: int,
     *     transfers_closed: int
     * }>
     */
    private function run(bool $dryRun, bool $mirror): array
    {
        /** @var array<string, array{customer: Customer, currency: string, available: int, synced: bool}> $groups */
        $groups = [];

        CustomerWallesterAccount::query()
            ->with('customer')
            ->whereNotNull('wallester_account_id')
            ->orderBy('customer_id')
            ->each(function (CustomerWallesterAccount $account) use (&$groups): void {
                $customer = $account->customer;

                if (! $customer instanceof Customer) {
                    return;
                }

                $currency = strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));
                $key = $customer->id.'|'.$currency;
                $synced = $this->syncAccount($account);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'customer' => $customer,
                        'currency' => $currency,
                        'available' => 0,
                        'synced' => true,
                    ];
                }

                $groups[$key]['synced'] = $groups[$key]['synced'] && $synced;

                if ($synced) {
                    $account->refresh();
                    $groups[$key]['available'] += (int) ($account->available_amount ?? 0);
                }
            });

        $results = [];

        foreach ($groups as $group) {
            $results[] = $this->alignGroup(
                customer: $group['customer'],
                currency: $group['currency'],
                wallesterCents: $group['available'],
                synced: $group['synced'],
                dryRun: $dryRun,
                mirror: $mirror,
            );
        }

        return $results;
    }

    /**
     * @return array{
     *     customer_id: int,
     *     customer_name: string,
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int|null,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool,
     *     pending_reversed_cents: int,
     *     transfers_closed: int
     * }
     */
    private function alignGroup(
        Customer $customer,
        string $currency,
        int $wallesterCents,
        bool $synced,
        bool $dryRun,
        bool $mirror,
    ): array {
        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', $currency)
            ->first();

        $fromCents = (int) ($balance?->wallester_allocated ?? 0);
        $fromWiseCents = (int) ($balance?->wise_allocated ?? 0);

        if (! $synced) {
            return [
                'customer_id' => (int) $customer->id,
                'customer_name' => (string) $customer->name,
                'currency' => $currency,
                'from_cents' => $fromCents,
                'from_wise_cents' => $fromWiseCents,
                'to_cents' => null,
                'delta_cents' => 0,
                'wise_delta_cents' => 0,
                'applied' => false,
                'wise_applied' => false,
                'synced' => false,
                'pending_reversed_cents' => 0,
                'transfers_closed' => 0,
            ];
        }

        $pendingReversed = 0;
        $transfersClosed = 0;

        if ($mirror) {
            $pendingReversed = $this->excessPendingCents((int) $customer->id, $currency, (int) ($balance?->pending ?? 0));
            $transfersClosed = $this->closeOpenWiseTransfers((int) $customer->id, $currency, $dryRun);

            if (! $dryRun && $pendingReversed > 0) {
                $this->ledger->reversePendingCredit(
                    customerId: (int) $customer->id,
                    currency: $currency,
                    amount: $pendingReversed,
                    description: self::PENDING_DESCRIPTION,
                    metadata: [
                        'source' => 'wallester_available',
                    ],
                );
            }
        }

        $delta = $wallesterCents - $fromCents;
        $wiseDelta = $wallesterCents - $fromWiseCents;
        $applied = false;
        $wiseApplied = false;

        if (! $dryRun && $delta !== 0) {
            $this->ledger->record(
                customerId: (int) $customer->id,
                currency: $currency,
                entryType: $delta > 0 ? LedgerEntryType::MANUAL_CREDIT : LedgerEntryType::MANUAL_DEBIT,
                amount: abs($delta),
                description: self::DESCRIPTION,
                metadata: [
                    'source' => 'wallester_available',
                ],
            );
            $applied = true;
        }

        if (! $dryRun && $wiseDelta !== 0) {
            CustomerBalance::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'currency' => $currency],
                ['wise_allocated' => $wallesterCents],
            );
            $wiseApplied = true;
        }

        return [
            'customer_id' => (int) $customer->id,
            'customer_name' => (string) $customer->name,
            'currency' => $currency,
            'from_cents' => $fromCents,
            'from_wise_cents' => $fromWiseCents,
            'to_cents' => $wallesterCents,
            'delta_cents' => $delta,
            'wise_delta_cents' => $wiseDelta,
            'applied' => $applied,
            'wise_applied' => $wiseApplied,
            'synced' => true,
            'pending_reversed_cents' => $pendingReversed,
            'transfers_closed' => $transfersClosed,
        ];
    }

    private function excessPendingCents(int $customerId, string $currency, int $pending): int
    {
        $protected = (int) StripeTransaction::query()
            ->where('customer_id', $customerId)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->where('partner_funding_status', PartnerFundingStatus::AwaitingStripePayout->value)
            ->whereNull('related_transaction_id')
            ->sum('net_amount');

        return max(0, $pending - $protected);
    }

    private function closeOpenWiseTransfers(int $customerId, string $currency, bool $dryRun): int
    {
        $ids = WiseTransfer::query()
            ->where('customer_id', $customerId)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->whereNull('settled_at')
            ->whereNull('refunded_at')
            ->pluck('id');

        if (! $dryRun && $ids->isNotEmpty()) {
            WiseTransfer::query()->whereIn('id', $ids)->update([
                'status' => WiseTransferStatus::Cancelled->value,
                'refunded_at' => now(),
            ]);
        }

        return $ids->count();
    }

    private function syncAccount(CustomerWallesterAccount $account): bool
    {
        try {
            $this->sync->syncAccount($account);

            return true;
        } catch (Throwable $exception) {
            Log::warning('align_customer_wallet_wallester_sync_failed', [
                'customer_id' => $account->customer_id,
                'wallester_account_id' => $account->wallester_account_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlignCustomerWalletsToWallesterService
{
    public const DESCRIPTION = 'Alinhamento ao saldo Wallester';

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
     *     synced: bool
     * }>
     */
    public function execute(bool $dryRun = false): array
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
     *     synced: bool
     * }
     */
    private function alignGroup(
        Customer $customer,
        string $currency,
        int $wallesterCents,
        bool $synced,
        bool $dryRun,
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
            ];
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
        ];
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

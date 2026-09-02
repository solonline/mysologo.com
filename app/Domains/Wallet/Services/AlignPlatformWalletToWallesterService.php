<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlignPlatformWalletToWallesterService
{
    public const DESCRIPTION = 'Alinhamento ao saldo Wallester master';

    public function __construct(
        protected WallesterMainAccountSyncService $sync,
        protected LedgerService $ledger,
    ) {}

    /**
     * @return list<array{
     *     currency: string,
     *     from_cents: int,
     *     to_cents: int,
     *     delta_cents: int,
     *     applied: bool,
     *     synced: bool
     * }>
     */
    public function execute(bool $dryRun = false): array
    {
        $platform = Customer::query()->where('type', 'platform')->first();

        if (! $platform instanceof Customer) {
            return [];
        }

        $results = [];

        $accounts = $platform->platformWallesterMainAccounts()->get();

        foreach ($accounts as $account) {
            $results[] = $this->alignAccount($platform, $account, $dryRun);
        }

        return $results;
    }

    /**
     * @return array{
     *     currency: string,
     *     from_cents: int,
     *     to_cents: int,
     *     delta_cents: int,
     *     applied: bool,
     *     synced: bool
     * }
     */
    private function alignAccount(Customer $platform, CustomerWallesterAccount $account, bool $dryRun): array
    {
        $synced = $this->syncAccount($account);
        $account->refresh();

        $currency = strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));
        $wallesterCents = (int) ($account->available_amount ?? 0);

        $balance = CustomerBalance::query()
            ->where('customer_id', $platform->id)
            ->where('currency', $currency)
            ->first();

        $fromCents = (int) ($balance?->available ?? 0);
        $delta = $wallesterCents - $fromCents;

        $applied = false;

        if ($delta !== 0 && ! $dryRun) {
            $this->ledger->record(
                customerId: (int) $platform->id,
                currency: $currency,
                entryType: $delta > 0 ? LedgerEntryType::MANUAL_CREDIT : LedgerEntryType::MANUAL_DEBIT,
                amount: abs($delta),
                description: self::DESCRIPTION,
                metadata: [
                    'source' => 'wallester_master',
                    'wallester_account_id' => $account->wallester_account_id,
                ],
            );
            $applied = true;
        }

        return [
            'currency' => $currency,
            'from_cents' => $fromCents,
            'to_cents' => $wallesterCents,
            'delta_cents' => $delta,
            'applied' => $applied,
            'synced' => $synced,
        ];
    }

    private function syncAccount(CustomerWallesterAccount $account): bool
    {
        try {
            $this->sync->syncAccount($account);

            return true;
        } catch (Throwable $exception) {
            Log::warning('align_platform_wallet_wallester_sync_failed', [
                'wallester_account_id' => $account->wallester_account_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

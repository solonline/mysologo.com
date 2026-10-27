<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterAccount;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlignPlatformWalletToWallesterService
{
    public const DESCRIPTION = 'Alinhamento ao saldo Wallester master';

    /** @var list<string> */
    private const CURRENCIES = ['EUR', 'USD'];

    public function __construct(
        protected WallesterMainAccountSyncService $sync,
        protected LedgerService $ledger,
        protected WallesterSettingsService $settings,
    ) {}

    /**
     * @return list<array{
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool
     * }>
     */
    public function execute(bool $dryRun = false): array
    {
        $results = [];

        foreach (self::CURRENCIES as $currency) {
            $account = $this->resolveMasterAccount($currency);

            if (! $account instanceof CustomerWallesterAccount) {
                continue;
            }

            $results[] = $this->alignAccount($account, $dryRun);
        }

        return $results;
    }

    /**
     * @return array{
     *     currency: string,
     *     from_cents: int,
     *     from_wise_cents: int,
     *     to_cents: int,
     *     delta_cents: int,
     *     wise_delta_cents: int,
     *     applied: bool,
     *     wise_applied: bool,
     *     synced: bool
     * }
     */
    private function alignAccount(CustomerWallesterAccount $account, bool $dryRun): array
    {
        $synced = $this->syncAccount($account);
        $account->refresh();

        $customer = $account->customer;
        $currency = strtoupper((string) ($account->funding_currency ?: $account->currency_code ?: 'EUR'));
        $wallesterCents = (int) ($account->available_amount ?? 0);

        $balance = $customer instanceof Customer
            ? CustomerBalance::query()
                ->where('customer_id', $customer->id)
                ->where('currency', $currency)
                ->first()
            : null;

        $fromCents = (int) ($balance?->wallester_allocated ?? 0);
        $fromWiseCents = (int) ($balance?->wise_allocated ?? 0);
        $delta = $wallesterCents - $fromCents;
        $wiseDelta = $wallesterCents - $fromWiseCents;
        $applied = false;
        $wiseApplied = false;

        if ($customer instanceof Customer && $delta !== 0 && ! $dryRun) {
            $this->ledger->record(
                customerId: (int) $customer->id,
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

        if ($customer instanceof Customer && $wiseDelta !== 0 && ! $dryRun) {
            CustomerBalance::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'currency' => $currency],
                ['wise_allocated' => $wallesterCents],
            );
            $wiseApplied = true;
        }

        return [
            'currency' => $currency,
            'from_cents' => $fromCents,
            'from_wise_cents' => $fromWiseCents,
            'to_cents' => $wallesterCents,
            'delta_cents' => $delta,
            'wise_delta_cents' => $wiseDelta,
            'applied' => $applied,
            'wise_applied' => $wiseApplied,
            'synced' => $synced,
        ];
    }

    private function resolveMasterAccount(string $currency): ?CustomerWallesterAccount
    {
        try {
            $accountId = $this->settings->platformMasterAccountId($currency);
        } catch (Throwable) {
            return null;
        }

        if ($accountId === '') {
            return null;
        }

        return CustomerWallesterAccount::query()
            ->where('wallester_account_id', $accountId)
            ->first();
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

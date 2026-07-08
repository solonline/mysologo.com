<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Backoffice\Services\StaffNotificationService;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerWallesterAccount;
use App\Models\StripeTransaction;
use Illuminate\Support\Facades\Cache;

class FundingLiquidityNotifier
{
    public function __construct(
        protected StaffNotificationService $staffNotifications,
        protected TopupWallesterFundingPolicy $policy,
    ) {}

    public function notifyIfNeeded(StripeTransaction $transaction, string $currency): void
    {
        if ($transaction->partner_funding_status !== PartnerFundingStatus::AwaitingLiquidity) {
            return;
        }

        $routingCurrency = strtoupper($currency);
        $cacheKey = 'funding_alert:'.$routingCurrency;

        if (! Cache::add($cacheKey, true, now()->addHour())) {
            return;
        }

        $blocked = StripeTransaction::query()
            ->where('type', TransactionType::TOPUP->value)
            ->where('status', 'succeeded')
            ->where('partner_funding_status', PartnerFundingStatus::AwaitingLiquidity->value)
            ->where('currency', $routingCurrency)
            ->get();

        $blockedTotal = (int) $blocked->sum('net_amount');
        $mainAccount = $this->resolveMainAccount($routingCurrency);

        $this->staffNotifications->notifyUsersWithPermission(
            type: 'funding.main_insufficient',
            title: 'Main Wallester sem saldo',
            permission: 'settings.edit',
            body: sprintf(
                '%s: %d top-up(s) bloqueado(s), total %s %.2f',
                $routingCurrency,
                $blocked->count(),
                $routingCurrency,
                $blockedTotal / 100,
            ),
            url: route('dashboard.settings'),
            data: [
                'currency' => $routingCurrency,
                'main_account_id' => $mainAccount?->wallester_account_id,
                'available_cents' => (int) ($mainAccount?->available_amount ?? 0),
                'blocked_total_cents' => $blockedTotal,
                'transaction_ids' => $blocked->pluck('id')->all(),
            ],
        );
    }

    private function resolveMainAccount(string $currency): ?CustomerWallesterAccount
    {
        return CustomerWallesterAccount::query()
            ->where('is_main', true)
            ->where('funding_currency', strtoupper($currency))
            ->first();
    }
}

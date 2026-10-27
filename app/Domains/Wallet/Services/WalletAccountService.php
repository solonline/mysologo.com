<?php

namespace App\Domains\Wallet\Services;

use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\WiseTransfer;

class WalletAccountService
{
    public function __construct(
        protected WallesterMainAccountSyncService $wallesterSync,
    ) {}

    /** @return array<string, mixed> */
    public function getDetails(Customer $customer): array
    {
        $stripeAccount = $customer->stripeAccount;
        $wallesterAccount = $customer->wallesterAccount;
        $spendableCents = $wallesterAccount
            ? $this->wiseAllocatedCents($customer, $wallesterAccount->funding_currency ?? $wallesterAccount->currency_code)
            : 0;

        return [
            'stripe' => $stripeAccount
                ? [
                    'stripe_customer_id' => $stripeAccount->stripe_customer_id,
                    'stripe_payment_method_id' => $stripeAccount->stripe_payment_method_id,
                    'currency' => $stripeAccount->currency,
                    'status' => $stripeAccount->status,
                    'funding_status' => $stripeAccount->funding_status,
                    'funding_status_reason' => $stripeAccount->funding_status_reason,
                    'viban' => $stripeAccount->viban,
                    'funding_type' => $stripeAccount->funding_type,
                    'bank_transfer_type' => $stripeAccount->bank_transfer_type,
                    'funding_iban' => $stripeAccount->funding_iban,
                    'funding_bic' => $stripeAccount->funding_bic,
                    'funding_account_holder_name' => $stripeAccount->funding_account_holder_name,
                    'funding_country' => $stripeAccount->funding_country,
                    'funding_financial_addresses' => $stripeAccount->funding_financial_addresses,
                ]
                : null,
            'wallester' => $wallesterAccount
                ? [
                    'id' => $wallesterAccount->id,
                    'wallester_account_id' => $wallesterAccount->wallester_account_id,
                    'iban' => $wallesterAccount->iban,
                    'viban' => $wallesterAccount->viban,
                    'swift_code' => $wallesterAccount->swift_code,
                    'receiver_name' => $wallesterAccount->receiver_name,
                    'bank_name' => $wallesterAccount->bank_name,
                    'currency_code' => $wallesterAccount->currency_code,
                    'status' => $wallesterAccount->status,
                    'balance' => $spendableCents,
                    'available_amount' => $spendableCents,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncWallesterBalance(Customer $customer, ?string $currency = null): ?array
    {
        if (! $this->wallesterSync->syncForCustomer($customer, $currency)) {
            return null;
        }

        return $this->getDetails($customer)['wallester'];
    }

    /** @return list<array<string, mixed>> */
    public function getBalances(Customer $customer, ?string $currency = null): array
    {
        $captiveByCurrency = WiseTransfer::query()
            ->where('customer_id', $customer->id)
            ->whereNull('settled_at')
            ->whereNull('refunded_at')
            ->when($currency, fn ($query) => $query->whereRaw('UPPER(currency) = ?', [strtoupper($currency)]))
            ->selectRaw('UPPER(currency) as currency, SUM(amount_cents) as captive_cents')
            ->groupByRaw('UPPER(currency)')
            ->pluck('captive_cents', 'currency');

        return CustomerBalance::where('customer_id', $customer->id)
            ->when($currency, fn ($q) => $q->whereRaw('UPPER(currency) = ?', [strtoupper($currency)]))
            ->get()
            ->map(fn ($b) => [
                'currency' => strtoupper($b->currency),
                'available' => (int) $b->wise_allocated,
                'pending' => (int) $b->pending,
                'captive' => (int) ($captiveByCurrency[strtoupper($b->currency)] ?? 0),
            ])
            ->values()
            ->toArray();
    }

    private function wiseAllocatedCents(Customer $customer, ?string $currency): int
    {
        $currency = strtoupper((string) ($currency ?: 'EUR'));

        return (int) (CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->value('wise_allocated') ?? 0);
    }
}

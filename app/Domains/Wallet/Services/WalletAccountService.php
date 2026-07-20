<?php

namespace App\Domains\Wallet\Services;

use App\Models\Customer;
use App\Models\CustomerBalance;

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
                    'balance' => $wallesterAccount->balance,
                    'available_amount' => $wallesterAccount->available_amount,
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
        return CustomerBalance::where('customer_id', $customer->id)
            ->when($currency, fn ($q) => $q->where('currency', strtolower($currency)))
            ->get()
            ->map(fn ($b) => [
                'currency' => strtoupper($b->currency),
                'available' => $b->available,
                'pending' => $b->pending,
            ])
            ->values()
            ->toArray();
    }
}

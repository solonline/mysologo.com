<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class AccountService
{
    public function __construct(protected StripeService $stripeService)
    {
    }

    public function getDetails(Customer $customer): array
    {
        $stripeAccount    = $customer->stripeAccount;
        $wallesterAccount = $customer->wallesterAccounts;

        return [
            'stripe'    => $stripeAccount
                ? [
                    'stripe_customer_id'      => $stripeAccount->stripe_customer_id,
                    'stripe_payment_method_id' => $stripeAccount->stripe_payment_method_id,
                    'currency'                => $stripeAccount->currency,
                    'status'                  => $stripeAccount->status,
                    'viban'                   => $stripeAccount->viban,
                    'funding_type'            => $stripeAccount->funding_type,
                    'bank_transfer_type'      => $stripeAccount->bank_transfer_type,
                    'funding_iban'           => $stripeAccount->funding_iban,
                    'funding_bic'            => $stripeAccount->funding_bic,
                    'funding_account_holder_name' => $stripeAccount->funding_account_holder_name,
                    'funding_country'        => $stripeAccount->funding_country,
                    'funding_financial_addresses' => $stripeAccount->funding_financial_addresses,
                ]
                : null,
            'wallester' => $wallesterAccount
                ? [
                    'wallester_account_id' => $wallesterAccount->wallester_account_id,
                    'iban'                 => $wallesterAccount->iban,
                    'viban'                => $wallesterAccount->viban,
                    'swift_code'           => $wallesterAccount->swift_code,
                    'receiver_name'        => $wallesterAccount->receiver_name,
                    'bank_name'            => $wallesterAccount->bank_name,
                    'currency_code'        => $wallesterAccount->currency_code,
                    'status'               => $wallesterAccount->status,
                ]
                : null,
        ];
    }

    public function getBalances(Customer $customer): array
    {
        $stripeBalance    = $this->getStripeBalance($customer);
        $wallesterBalance = $this->getWallesterBalance($customer);

        return [
            'stripe'    => $stripeBalance,
            'wallester' => $wallesterBalance,
        ];
    }

    private function getStripeBalance(Customer $customer): ?array
    {
        $stripeAccount = $customer->stripeAccount;

        if (! $stripeAccount) {
            return null;
        }

        try {
            return $this->stripeService->getCashBalance($stripeAccount->stripe_customer_id);
        } catch (ApiException $e) {
            Log::warning('Falha ao obter saldo Stripe para account details', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getWallesterBalance(Customer $customer): ?array
    {
        $wallesterAccount = $customer->wallesterAccounts;

        if (! $wallesterAccount) {
            return null;
        }

        return [
            'balance'          => $wallesterAccount->balance,
            'available_amount' => $wallesterAccount->available_amount,
            'blocked_amount'   => $wallesterAccount->blocked_amount,
            'credit_limit'     => $wallesterAccount->credit_limit,
            'used_credit'      => $wallesterAccount->used_credit,
            'currency_code'    => $wallesterAccount->currency_code,
        ];
    }
}

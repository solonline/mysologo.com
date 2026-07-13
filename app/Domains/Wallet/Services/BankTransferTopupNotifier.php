<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Jobs\SendBankTransferTopupReceivedEmail;
use App\Models\StripeTransaction;

class BankTransferTopupNotifier
{
    public function notifyIfEligible(StripeTransaction $transaction): void
    {
        if (! $this->isBankTransferCashBalanceTopup($transaction)) {
            return;
        }

        $customer = $transaction->customer;

        if ($customer === null || trim((string) $customer->email) === '') {
            return;
        }

        SendBankTransferTopupReceivedEmail::dispatch($transaction->id);
    }

    public function isBankTransferCashBalanceTopup(StripeTransaction $transaction): bool
    {
        if ($transaction->stripe_cash_balance_transaction_id === null) {
            return false;
        }

        if ($transaction->type !== TransactionType::TOPUP) {
            return false;
        }

        return $transaction->payment_method_type === 'bank_transfer';
    }
}

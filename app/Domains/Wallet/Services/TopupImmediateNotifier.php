<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Customer\Notifications\NotificationDispatcher;
use App\Domains\Customer\Notifications\TopupImmediateNotification;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;

class TopupImmediateNotifier
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function notifyIfEligible(StripeTransaction $transaction): void
    {
        if (! $this->isBankTransferCashBalanceTopup($transaction)) {
            return;
        }

        $customer = $transaction->customer;

        if ($customer === null) {
            return;
        }

        $amountCents = max(0, (int) ($transaction->wallester_funding_net_amount ?? $transaction->net_amount ?? $transaction->amount));
        $currency = strtoupper((string) $transaction->currency);
        $amountLabel = number_format($amountCents / 100, 2, ',', '.').' '.$currency;

        $this->dispatcher->send(
            $customer,
            new TopupImmediateNotification(
                amountLabel: $amountLabel,
                transactionId: (int) $transaction->id,
                description: filled($transaction->description) ? (string) $transaction->description : null,
                extra: [
                    'stripe_transaction_id' => (int) $transaction->id,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'amount_label' => $amountLabel,
                    'bank_transfer_sender_name' => $transaction->bank_transfer_sender_name,
                    'bank_transfer_reference' => $transaction->bank_transfer_reference,
                ],
            ),
        );
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

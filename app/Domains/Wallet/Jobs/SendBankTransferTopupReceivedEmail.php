<?php

namespace App\Domains\Wallet\Jobs;

use App\Domains\Wallet\Mail\BankTransferTopupReceivedMail;
use App\Models\StripeTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBankTransferTopupReceivedEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $stripeTransactionId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $transaction = StripeTransaction::query()
            ->with('customer')
            ->find($this->stripeTransactionId);

        if ($transaction === null || $transaction->customer === null) {
            return;
        }

        $email = trim((string) $transaction->customer->email);

        if ($email === '') {
            return;
        }

        $amountCents = max(0, (int) ($transaction->wallester_funding_net_amount ?? $transaction->net_amount ?? $transaction->amount));
        $currency = strtoupper((string) $transaction->currency);
        $formattedAmount = number_format($amountCents / 100, 2, ',', '.');

        Mail::to($email)->send(new BankTransferTopupReceivedMail(
            customer: $transaction->customer,
            transaction: $transaction,
            formattedAmount: $formattedAmount,
            currency: $currency,
        ));
    }
}

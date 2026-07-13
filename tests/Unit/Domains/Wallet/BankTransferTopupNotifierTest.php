<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Jobs\SendBankTransferTopupReceivedEmail;
use App\Domains\Wallet\Services\BankTransferTopupNotifier;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class BankTransferTopupNotifierTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_notifier_dispatches_email_for_bank_transfer_cash_balance_topup(): void
    {
        Bus::fake([SendBankTransferTopupReceivedEmail::class]);

        $customer = $this->createRetailCustomer(['email' => 'topup@example.com']);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 9_900,
            'wallester_funding_net_amount' => 9_900,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_notify_1',
            'partner_funding_status' => PartnerFundingStatus::Completed,
        ]);

        app(BankTransferTopupNotifier::class)->notifyIfEligible($transaction);

        Bus::assertDispatched(SendBankTransferTopupReceivedEmail::class, function (SendBankTransferTopupReceivedEmail $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });
    }

    public function test_notifier_ignores_card_topups(): void
    {
        Bus::fake([SendBankTransferTopupReceivedEmail::class]);

        $customer = $this->createRetailCustomer();

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 5_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 4_875,
            'wallester_funding_net_amount' => 4_875,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Completed,
        ]);

        app(BankTransferTopupNotifier::class)->notifyIfEligible($transaction);

        Bus::assertNotDispatched(SendBankTransferTopupReceivedEmail::class);
    }
}

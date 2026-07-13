<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Jobs\SendBankTransferTopupReceivedEmail;
use App\Domains\Wallet\Mail\BankTransferTopupReceivedMail;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class BankTransferTopupEmailTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallesterSettings();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_bank_transfer_topup_job_sends_email_after_wallester_funding_completes(): void
    {
        Bus::fake([SendBankTransferTopupReceivedEmail::class]);
        Mail::fake();

        $customer = $this->createRetailCustomer(['email' => 'bank-topup@example.com']);
        $platform = $this->createPlatformCustomer();
        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_bank_topup');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 10_000,
            'stripe_fee' => 0,
            'platform_fee' => 100,
            'net_amount' => 9_900,
            'immediate_gross_amount' => 10_000,
            'wallester_funding_net_amount' => 9_900,
            'stripe_deferred_net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_email_1',
            'bank_transfer_sender_name' => 'Mario Rossi',
            'bank_transfer_reference' => 'REF-123',
            'description' => 'Bank transfer top-up via VIBAN',
            'partner_funding_status' => PartnerFundingStatus::Pending,
            'balance_settled_at' => now(),
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 9_900,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->andReturn(['id' => 'pay_bank_topup_1']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        (new FundWallesterTopupFromStripeJob($transaction->id))
            ->handle(app(FundingPartnerMovementService::class));

        Bus::assertDispatched(SendBankTransferTopupReceivedEmail::class, function (SendBankTransferTopupReceivedEmail $job) use ($transaction): bool {
            return $job->stripeTransactionId === $transaction->id;
        });

        (new SendBankTransferTopupReceivedEmail($transaction->id))->handle();

        Mail::assertSent(BankTransferTopupReceivedMail::class, function (BankTransferTopupReceivedMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->email)
                && $mail->formattedAmount === '99,00'
                && $mail->currency === 'EUR';
        });
    }

    private function createPlatformCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'email' => 'master@yoop.com',
            'username' => 'platform',
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }
}

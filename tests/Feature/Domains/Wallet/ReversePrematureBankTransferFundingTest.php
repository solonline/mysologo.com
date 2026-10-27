<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\LedgerService;
use App\Domains\Wallet\Services\ReversePrematureBankTransferFunding;
use App\Models\CustomerBalance;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class ReversePrematureBankTransferFundingTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_return_moves_funds_back_without_a_history_line_and_resets_the_transfer(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'bank_transfer',
            'direction' => 'credit',
            'amount' => 300,
            'stripe_fee' => 2,
            'platform_fee' => 0,
            'net_amount' => 298,
            'stripe_deferred_net_amount' => 0,
            'wallester_funding_net_amount' => 298,
            'immediate_gross_amount' => 300,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'stripe_charge_id' => 'ch_57',
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funded_at' => now(),
            'available_on' => '2026-10-21',
            'balance_settled_at' => now(),
            'stripe_cash_balance_transaction_id' => 'ccsbtxn_57',
        ]);

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_return');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 298,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':in',
            'description' => 'Stripe top-up #'.$transaction->id,
        ]);

        $ledger = app(LedgerService::class);
        $ledger->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 298,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );
        $ledger->releasePendingToAvailable(
            customerId: $customer->id,
            currency: 'EUR',
            amount: 298,
            reference: $transaction,
            description: 'Wallester top-up funding released #'.$transaction->id,
        );

        $driver = Mockery::mock(WallesterFundingTransferDriver::class);
        $driver->shouldReceive('requireActiveAccount')->once()->andReturn($account);
        $driver->shouldReceive('getPlatformMasterAccount')->once()->andReturn($account);
        $driver->shouldReceive('executeTransfer')
            ->once()
            ->withArgs(function ($from, $to, int $amountCents): bool {
                return $amountCents === 298;
            })
            ->andReturn(['id' => 'tr_return']);
        $this->app->instance(WallesterFundingTransferDriver::class, $driver);

        $reversed = app(ReversePrematureBankTransferFunding::class)->reverse($transaction->fresh());

        $transaction->refresh();

        $this->assertTrue($reversed);
        $this->assertSame(0, FundingPartnerMovement::query()->count());
        $this->assertSame(TransactionType::BANK_TRANSFER, $transaction->type);
        $this->assertSame(PartnerFundingStatus::NotRequired, $transaction->partner_funding_status);
        $this->assertNull($transaction->balance_settled_at);
        $this->assertNull($transaction->partner_funded_at);
        $this->assertSame(0, (int) $transaction->wallester_funding_net_amount);
        $this->assertSame(298, (int) $transaction->stripe_deferred_net_amount);
        $this->assertSame(0, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wallester_allocated'));
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'amount' => 298,
            'description' => 'Stripe bank transfer funding unwind #'.$transaction->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\CustomerBalance;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WalletAuditCommandTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallesterSettings();
    }

    public function test_audit_reports_uncalculated_fees_and_missing_ledger(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 0,
            'pending' => 4_731,
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::CARD,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 0,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::CARD,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 58,
            'platform_fee' => 5,
            'net_amount' => 937,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertFailed()
            ->expectsOutputToContain('UNCALCULATED_FEES')
            ->expectsOutputToContain('MISSING_PAYMENT_LEDGER')
            ->expectsOutputToContain('STALE_PENDING_BALANCE')
            ->expectsOutputToContain('UNCREDITED_NET_AMOUNT');
    }

    public function test_audit_reports_fee_mismatch(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::CARD,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 50,
            'platform_fee' => 10,
            'net_amount' => 900,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertFailed()
            ->expectsOutputToContain('FEE_SUM_MISMATCH');
    }

    public function test_audit_passes_for_consistent_topup_and_ledger(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_audit');

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 100,
            'platform_fee' => 50,
            'net_amount' => 1_850,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'balance_settled_at' => now(),
        ]);

        LedgerEntry::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => 'credit',
            'amount' => 1_850,
            'balance_after' => 1_850,
            'entry_type' => LedgerEntryType::PAYMENT_RECEIVED,
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 1_850,
            'pending' => 0,
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account->getMorphClass(),
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 1_850,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$transaction->id.':out',
        ]);

        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No issues found');
    }

    public function test_audit_reports_partner_funding_status_findings(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_partner_audit');

        $awaiting = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingLiquidity,
            'partner_funding_failure_reason' => 'not enough funds',
            'balance_settled_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account->getMorphClass(),
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2_000,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Failed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$awaiting->id.':out',
            'failure_reason' => 'not enough funds',
        ]);

        $failed = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_500,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 1_500,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Failed,
            'partner_funding_failure_reason' => 'permanent error',
            'balance_settled_at' => now(),
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account->getMorphClass(),
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 1_500,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Failed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe_topup:'.$failed->id.':out',
            'failure_reason' => 'permanent error',
        ]);

        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertFailed()
            ->expectsOutputToContain('AWAITING_PLATFORM_LIQUIDITY')
            ->expectsOutputToContain('FAILED_WALLESTER_TOPUP');
    }

    public function test_fix_option_is_not_implemented(): void
    {
        $this->artisan('wallet:audit', ['--fix' => true])
            ->assertFailed()
            ->expectsOutputToContain('not implemented');
    }

    public function test_audit_warns_when_database_has_no_wallet_data(): void
    {
        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No wallet data found');
    }
}

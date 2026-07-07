<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Models\FundingPartnerMovement;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class FundingPartnerMovementRetryTest extends TestCase
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

    public function test_failed_movement_retry_reuses_rows_without_unique_violation(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $platform = $this->createRetailCustomer([
            'email' => 'master@yoop.com',
            'username' => 'platform',
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
        ]);
        $toAccount = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_retry_customer');

        $transaction = Transaction::query()->create([
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
            'partner_funding_status' => PartnerFundingStatus::Failed,
            'balance_settled_at' => now(),
        ]);

        app(LedgerService::class)->recordPendingCredit(
            customerId: $customer->id,
            currency: 'EUR',
            entryType: LedgerEntryType::PAYMENT_RECEIVED,
            amount: 2_000,
            reference: $transaction,
            description: 'Stripe payment #'.$transaction->id,
        );

        $groupId = (string) Str::uuid();
        $idempotencyKey = 'stripe_topup:'.$transaction->id;

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $master->getMorphClass(),
            'funding_account_id' => $master->id,
            'external_account_id' => $master->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2_000,
            'currency' => 'EUR',
            'transfer_group_id' => $groupId,
            'counterparty_external_account_id' => $toAccount->wallester_account_id,
            'status' => FundingPartnerMovementStatus::Failed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $idempotencyKey.':out',
            'failure_reason' => 'not enough funds',
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $toAccount->getMorphClass(),
            'funding_account_id' => $toAccount->id,
            'external_account_id' => $toAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2_000,
            'currency' => 'EUR',
            'transfer_group_id' => $groupId,
            'counterparty_external_account_id' => $master->wallester_account_id,
            'status' => FundingPartnerMovementStatus::Failed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => $idempotencyKey.':in',
            'failure_reason' => 'not enough funds',
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->andReturn(['id' => 'pay_retry_ok']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        app(FundingPartnerMovementService::class)->retryTransferFromMasterToCustomer(
            to: $customer,
            amountCents: 2_000,
            currency: 'EUR',
            description: 'Stripe top-up #'.$transaction->id,
            idempotencyKey: $idempotencyKey,
            reference: $transaction,
        );

        $this->assertDatabaseCount('funding_partner_movements', 2);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => $idempotencyKey.':out',
            'status' => FundingPartnerMovementStatus::Completed->value,
            'failure_reason' => null,
        ]);
    }
}

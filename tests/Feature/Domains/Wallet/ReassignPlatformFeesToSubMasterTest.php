<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Services\ReassignPlatformFeesToSubMasterService;
use App\Models\Customer;
use App\Models\FundingPartnerMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class ReassignPlatformFeesToSubMasterTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const FEE_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_reassigns_fx_in_rows_and_transfers_total_without_1_to_2_movement(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'master@yoop.test']);
        $retail = $this->createRetailCustomer();

        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 439,
            'balance' => 439,
        ]);
        $fee = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'receiver_name' => 'Sub Conta Master',
            'available_amount' => 0,
            'balance' => 0,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);
        $retailAccount = $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_retail');

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $retailAccount::class,
            'funding_account_id' => $retailAccount->id,
            'external_account_id' => $retailAccount->wallester_account_id,
            'counterparty_external_account_id' => self::MASTER_ID,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:auth-1:out',
            'description' => 'Câmbio Yoop',
        ]);
        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $master::class,
            'funding_account_id' => $master->id,
            'external_account_id' => self::MASTER_ID,
            'counterparty_external_account_id' => $retailAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 5,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:auth-1:in',
            'description' => 'Câmbio Yoop',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::MASTER_ID
                    && $payload['to_account_id'] === self::FEE_ID
                    && $payload['amount'] === 0.05;
            }), 'platform')
            ->andReturn(['id' => 'pay_reclass']);
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ID, 'platform')
            ->andReturn(['available_amount' => 4.34, 'balance' => 4.34]);
        $gateway->shouldReceive('getAccount')
            ->with(self::FEE_ID, 'platform')
            ->andReturn(['available_amount' => 0.05, 'balance' => 0.05]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $beforeCount = FundingPartnerMovement::query()->count();

        $result = app(ReassignPlatformFeesToSubMasterService::class)->execute();

        $this->assertSame(5, $result['amount_cents']);
        $this->assertTrue($result['transferred']);
        $this->assertSame($beforeCount, FundingPartnerMovement::query()->count());

        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:auth-1:in',
            'funding_account_id' => $fee->id,
            'external_account_id' => self::FEE_ID,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:auth-1:out',
            'counterparty_external_account_id' => self::FEE_ID,
        ]);
        $this->assertSame(434, (int) $master->fresh()->available_amount);
        $this->assertSame(5, (int) $fee->fresh()->available_amount);
    }

    public function test_dry_run_does_not_transfer_or_reassign(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform']);
        $master = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
        ]);
        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $master::class,
            'funding_account_id' => $master->id,
            'external_account_id' => self::MASTER_ID,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 12,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'fx-platform:dry:in',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('accountTransfer');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(ReassignPlatformFeesToSubMasterService::class)->execute(dryRun: true);

        $this->assertSame(12, $result['amount_cents']);
        $this->assertFalse($result['transferred']);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:dry:in',
            'funding_account_id' => $master->id,
        ]);
    }
}

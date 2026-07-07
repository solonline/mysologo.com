<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class FundingPartnerMovementServiceTest extends TestCase
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

    public function test_transfer_is_idempotent_by_key(): void
    {
        $from = $this->createRetailCustomer(['username' => 'from_user']);
        $to = $this->createRetailCustomer(['username' => 'to_user']);

        $this->createWallesterAccount($from, IntegrationAccountStatus::ACTIVE, 'wal_from');
        $this->createWallesterAccount($to, IntegrationAccountStatus::ACTIVE, 'wal_to');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')->once()->andReturn(['available_amount' => 50.00]);
        $gateway->shouldReceive('accountTransfer')->once()->andReturn(['id' => 'pay_once']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $service = app(FundingPartnerMovementService::class);

        $service->transferBetweenCustomers(
            from: $from,
            to: $to,
            amountCents: 1000,
            currency: 'EUR',
            description: 'Test',
            idempotencyKey: 'transfer:test:1',
        );

        $service->transferBetweenCustomers(
            from: $from,
            to: $to,
            amountCents: 1000,
            currency: 'EUR',
            description: 'Test',
            idempotencyKey: 'transfer:test:1',
        );

        $this->assertDatabaseCount('funding_partner_movements', 2);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'transfer:test:1:out',
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);
    }

    public function test_wallester_driver_converts_decimal_amounts_to_cents(): void
    {
        $driver = new WallesterFundingTransferDriver(
            Mockery::mock(WallesterGatewayInterface::class),
            new PlatformMasterCurrencyResolver,
            app(WallesterSettingsService::class),
        );

        $this->assertSame(1234, $driver->amountToCents(12.34));
        $this->assertSame(0, $driver->amountToCents(null));
    }
}

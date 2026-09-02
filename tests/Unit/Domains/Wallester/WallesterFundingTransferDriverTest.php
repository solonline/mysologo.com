<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterFundingTransferDriverTest extends TestCase
{
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

    public function test_get_platform_master_account_resolves_eur_and_usd(): void
    {
        $platform = Customer::factory()->create([
            'type' => 'platform',
            'email' => 'platform@test.com',
        ]);

        $eurMain = CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => 'master-eur-uuid',
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'product_id' => 'prod_eur',
            'company_id' => 'comp_eur',
            'is_main' => true,
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $usdMain = CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => 'master-usd-uuid',
            'currency_code' => 'USD',
            'funding_currency' => 'USD',
            'product_id' => 'prod_usd',
            'company_id' => 'comp_usd',
            'is_main' => true,
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $driver = new WallesterFundingTransferDriver(
            $this->mockWallesterGateway(),
            new PlatformMasterCurrencyResolver,
            app(WallesterSettingsService::class),
        );

        $this->assertTrue($driver->getPlatformMasterAccount('EUR')->is($eurMain));
        $this->assertTrue($driver->getPlatformMasterAccount('USD')->is($usdMain));
        $this->assertTrue($driver->getPlatformMasterAccount('GBP')->is($eurMain));
    }

    public function test_get_platform_fee_account_uses_sub_master_when_configured(): void
    {
        $platform = Customer::factory()->create([
            'type' => 'platform',
            'email' => 'platform-fee@test.com',
        ]);

        CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => 'master-eur-uuid',
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'product_id' => 'prod_eur',
            'company_id' => 'comp_eur',
            'is_main' => true,
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $fee = CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => '626c80a3-f8e7-4627-8bcf-ca73f9944abf',
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'product_id' => 'prod_fee_eur',
            'company_id' => 'comp_fee_eur',
            'is_main' => false,
            'receiver_name' => 'Sub Conta Master',
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => '626c80a3-f8e7-4627-8bcf-ca73f9944abf',
        ]);

        $driver = new WallesterFundingTransferDriver(
            $this->mockWallesterGateway(),
            new PlatformMasterCurrencyResolver,
            app(WallesterSettingsService::class),
        );

        $this->assertTrue($driver->getPlatformFeeAccount('EUR')->is($fee));
    }

    public function test_get_platform_fee_account_falls_back_to_master_when_missing(): void
    {
        $platform = Customer::factory()->create(['type' => 'platform']);

        $eurMain = CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => '75d92dc4-6de9-4580-8b62-3cbd3513af56',
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'product_id' => 'prod_eur_fallback',
            'company_id' => 'comp_eur_fallback',
            'is_main' => true,
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $driver = new WallesterFundingTransferDriver(
            $this->mockWallesterGateway(),
            new PlatformMasterCurrencyResolver,
            app(WallesterSettingsService::class),
        );

        $this->assertTrue($driver->getPlatformFeeAccount('EUR')->is($eurMain));
    }

    public function test_get_platform_master_account_uses_configured_id_from_settings(): void
    {
        $platform = Customer::factory()->create(['type' => 'platform']);

        $configured = CustomerWallesterAccount::query()->create([
            'customer_id' => $platform->id,
            'wallester_account_id' => 'configured-usd-uuid',
            'currency_code' => 'USD',
            'funding_currency' => 'USD',
            'product_id' => 'prod_config_usd',
            'company_id' => 'comp_config_usd',
            'is_main' => true,
            'status' => IntegrationAccountStatus::ACTIVE,
        ]);

        $driver = new WallesterFundingTransferDriver(
            $this->mockWallesterGateway(),
            new PlatformMasterCurrencyResolver,
            app(WallesterSettingsService::class),
        );

        $this->assertTrue($driver->getPlatformMasterAccount('USD')->is($configured));
    }
}

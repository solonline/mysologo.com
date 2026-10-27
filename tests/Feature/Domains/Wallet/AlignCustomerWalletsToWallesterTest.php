<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\AlignCustomerWalletsToWallesterService;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class AlignCustomerWalletsToWallesterTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_sets_ledger_and_wise_to_wallester_available(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_customer', [
            'available_amount' => 1000,
            'balance' => 1500,
            'blocked_amount' => 500,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_customer', 'platform')
            ->andReturn([
                'available_amount' => 7.50,
                'balance' => 12.50,
                'blocked_amount' => 5.00,
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester')->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(750, (int) $balance->wallester_allocated);
        $this->assertSame(750, (int) $balance->wise_allocated);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
            'amount' => 250,
            'description' => AlignCustomerWalletsToWallesterService::DESCRIPTION,
        ]);
    }

    public function test_dry_run_does_not_change_balances(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer_dry']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_dry', [
            'available_amount' => 1000,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_dry', 'platform')
            ->andReturn(['available_amount' => 7.50, 'balance' => 7.50]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester', ['--dry-run' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(500, (int) $balance->wallester_allocated);
        $this->assertSame(200, (int) $balance->wise_allocated);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_failed_wallester_sync_does_not_change_balances(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer_fail']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_fail', [
            'available_amount' => 1000,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_fail', 'platform')
            ->andThrow(new RuntimeException('wallester down'));
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester')->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(500, (int) $balance->wallester_allocated);
        $this->assertSame(200, (int) $balance->wise_allocated);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }
}

<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Services\AlignPlatformWalletToWallesterService;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class AlignPlatformWalletToWallesterTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_debits_platform_wallet_down_to_wallester_master(): void
    {
        $this->seedWallesterSettings();

        $platform = Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-align@yoop.test',
            'username' => 'platform_align',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, '75d92dc4-6de9-4580-8b62-3cbd3513af56', [
            'is_main' => true,
            'available_amount' => 1598,
            'balance' => 1598,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 1598,
            'wise_allocated' => 800,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('75d92dc4-6de9-4580-8b62-3cbd3513af56', 'platform')
            ->andReturn(['available_amount' => 5.22, 'balance' => 5.22]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-platform-to-wallester')
            ->assertSuccessful();

        $balance = CustomerBalance::query()
            ->where('customer_id', $platform->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertSame(522, (int) $balance?->wallester_allocated);
        $this->assertSame(522, (int) $balance?->wise_allocated);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $platform->id,
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'amount' => 1076,
            'description' => AlignPlatformWalletToWallesterService::DESCRIPTION,
        ]);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->seedWallesterSettings();

        $platform = Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-align-dry@yoop.test',
            'username' => 'platform_align_dry',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, '75d92dc4-6de9-4580-8b62-3cbd3513af56', [
            'is_main' => true,
            'available_amount' => 1598,
            'balance' => 1598,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 1598,
            'wise_allocated' => 800,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('75d92dc4-6de9-4580-8b62-3cbd3513af56', 'platform')
            ->andReturn(['available_amount' => 5.22, 'balance' => 5.22]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-platform-to-wallester', ['--dry-run' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()
            ->where('customer_id', $platform->id)
            ->first();

        $this->assertSame(1598, (int) $balance?->wallester_allocated);
        $this->assertSame(800, (int) $balance?->wise_allocated);

        $this->assertSame(0, LedgerEntry::query()
            ->where('customer_id', $platform->id)
            ->count());
    }
}

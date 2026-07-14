<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerWallesterAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterBalanceSyncTest extends TestCase
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

    public function test_customer_can_sync_wallester_balance_via_api(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sync_balance', [
            'available_amount' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_sync_balance', (string) $customer->id)
            ->andReturn(['available_amount' => 250.50, 'balance' => 250.50]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallet/account/wallester/sync')
            ->assertOk()
            ->assertJsonPath('wallester.available_amount', 25_050);

        $this->assertSame(25_050, (int) $account->fresh()->available_amount);
    }

    public function test_p2p_transfer_persists_wallester_balance_from_partner(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_sync']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_sync']);

        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender_sync', [
            'available_amount' => 0,
        ]);
        $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient_sync');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-sync-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->twice()
            ->with('wal_sender_sync', (string) $sender->id)
            ->andReturn(['available_amount' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_recipient_sync', (string) $recipient->id)
            ->andReturn(['available_amount' => 25.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->andReturn(['id' => 'pay_sync_1']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_sync',
                'amount' => 2500,
                'currency' => 'EUR',
                'device_token' => 'device-sync-1',
                'pin' => '1234',
            ])
            ->assertCreated();

        $this->assertSame(10_000, (int) $senderAccount->fresh()->available_amount);

        $recipientAccount = CustomerWallesterAccount::query()
            ->where('wallester_account_id', 'wal_recipient_sync')
            ->first();
        $this->assertSame(2_500, (int) $recipientAccount?->available_amount);
    }

    private function createActiveCustomer(array $overrides = []): Customer
    {
        return $this->createRetailCustomer(array_merge([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ], $overrides));
    }
}

<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerDevice;
use App\Models\CustomerWallesterAccount;
use App\Models\WiseTransfer;
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
            'balance' => 0,
        ]);
        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 25_050,
            'pending' => 300,
            'wise_allocated' => 12_000,
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
            ->assertJsonPath('wallester.available_amount', 12_000)
            ->assertJsonPath('wallester.balance', 12_000);

        $this->assertSame(25_050, (int) $account->fresh()->available_amount);

        $this->withToken($token)
            ->getJson('/api/wallet/account/balance')
            ->assertOk()
            ->assertJsonPath('data.0.available', 12_000)
            ->assertJsonPath('data.0.pending', 300)
            ->assertJsonPath('data.0.captive', 0);

        $this->withToken($token)
            ->getJson('/api/wallet/account')
            ->assertOk()
            ->assertJsonPath('data.wallester.available_amount', 12_000)
            ->assertJsonPath('data.wallester.balance', 12_000);

        $this->withToken($token)
            ->getJson('/api/wallet/dashboard')
            ->assertOk()
            ->assertJsonPath('data.balances.0.available', 12_000)
            ->assertJsonPath('data.balances.0.captive', 0);
    }

    public function test_balance_includes_captive_wise_amount_for_the_currency(): void
    {
        $customer = $this->createActiveCustomer();
        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 125,
            'pending' => 0,
            'wise_allocated' => 125,
        ]);

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440842090,
            'wise_batch_group_id' => '64a6d684-3be0-4b40-b3e9-4e22f6f8e9f7',
            'wise_recipient_id' => 1612703775,
            'beneficiary_name' => 'TAMIETTO JUNIOR, UNIPESSOAL, LDA',
            'reference' => 'Tamietto 1 EUR',
            'currency' => 'EUR',
            'amount_cents' => 100,
            'idempotency_key' => 'wise_transfer:balance-captive',
            'status' => 'incoming_payment_waiting',
        ]);

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440842091,
            'wise_batch_group_id' => '64a6d684-3be0-4b40-b3e9-4e22f6f8e9f8',
            'wise_recipient_id' => 1612703776,
            'beneficiary_name' => 'Ja enviado',
            'reference' => 'Enviado',
            'currency' => 'EUR',
            'amount_cents' => 500,
            'idempotency_key' => 'wise_transfer:balance-settled',
            'status' => 'outgoing_payment_sent',
            'settled_at' => now(),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->getJson('/api/wallet/account/balance?currency=eur')
            ->assertOk()
            ->assertJsonPath('data.0.currency', 'EUR')
            ->assertJsonPath('data.0.available', 125)
            ->assertJsonPath('data.0.captive', 100);
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
            ->withHeaders(['Idempotency-Key' => 'p2p-sync-1'])
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

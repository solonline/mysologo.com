<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class P2pFundingPartnerTransferTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_p2p_transfers_via_wallester_and_mirrors_ledger(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_one']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_one']);

        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender');
        $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-token-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_sender', (string) $sender->id)
            ->andReturn(['available_amount' => 100.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === 'wal_sender'
                    && $payload['to_account_id'] === 'wal_recipient'
                    && $payload['amount'] === 25.0;
            }), (string) $sender->id)
            ->andReturn(['id' => 'pay_p2p_1']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_one',
                'amount' => 2500,
                'currency' => 'EUR',
                'description' => 'Test transfer',
                'device_token' => 'device-token-1',
                'pin' => '1234',
            ])
            ->assertCreated()
            ->assertJsonPath('debit.amount', 2500)
            ->assertJsonPath('credit.amount', 2500);

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $senderAccount->id,
            'direction' => 'out',
            'amount' => 2500,
            'status' => FundingPartnerMovementStatus::Completed->value,
            'partner_external_id' => 'pay_p2p_1',
        ]);

        $this->assertDatabaseHas('stripe_transactions', [
            'customer_id' => $sender->id,
            'type' => 'p2p',
            'direction' => 'debit',
            'amount' => 2500,
        ]);
    }

    public function test_p2p_rejects_insufficient_wallester_balance(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_low']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_low']);

        $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender_low');
        $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient_low');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-token-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->andReturn(['available_amount' => 10.00]);
        $gateway->shouldNotReceive('accountTransfer');

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_low',
                'amount' => 2500,
                'currency' => 'EUR',
                'device_token' => 'device-token-1',
                'pin' => '1234',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'insufficient_balance');
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

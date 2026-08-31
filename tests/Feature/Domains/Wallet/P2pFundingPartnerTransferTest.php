<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_p2p_requires_idempotency_key(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_no_key']);
        $this->createActiveCustomer(['username' => 'recipient_no_key']);

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-token-1',
            'pin' => bcrypt('1234'),
        ]);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_no_key',
                'amount' => 2500,
                'currency' => 'EUR',
                'device_token' => 'device-token-1',
                'pin' => '1234',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Idempotency-Key required');
    }

    public function test_p2p_transfers_via_wallester_and_mirrors_ledger(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_one']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_one']);

        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender');
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-token-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->twice()
            ->with('wal_sender', (string) $sender->id)
            ->andReturn(['available_amount' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_recipient', (string) $recipient->id)
            ->andReturn(['available_amount' => 25.00]);
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
            ->withHeaders(['Idempotency-Key' => 'p2p-test-1'])
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
            'idempotency_key' => 'p2p:p2p-test-1:out',
        ]);

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $recipientAccount->id,
            'direction' => 'in',
            'partner_external_id' => 'pay_p2p_1',
            'idempotency_key' => 'p2p:p2p-test-1:in',
        ]);

        $this->assertDatabaseHas('stripe_transactions', [
            'customer_id' => $sender->id,
            'type' => 'p2p',
            'direction' => 'debit',
            'amount' => 2500,
        ]);

        $this->assertDatabaseMissing('customer_favorites', [
            'customer_id' => $sender->id,
            'favorite_customer_id' => $recipient->id,
        ]);
    }

    public function test_p2p_saves_recipient_as_favorite_when_requested(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_fav']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_fav']);

        $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender_fav');
        $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient_fav');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-fav-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')->andReturn(['available_amount' => 100.00]);
        $gateway->shouldReceive('accountTransfer')->once()->andReturn(['id' => 'pay_p2p_fav']);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'p2p-fav-1'])
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_fav',
                'amount' => 2500,
                'currency' => 'EUR',
                'device_token' => 'device-fav-1',
                'pin' => '1234',
                'favorite' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('customer_favorites', [
            'customer_id' => $sender->id,
            'favorite_customer_id' => $recipient->id,
        ]);
    }

    public function test_p2p_retry_with_same_key_does_not_transfer_again(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_retry']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_retry']);

        $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender_retry');
        $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient_retry');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-retry-1',
            'pin' => bcrypt('1234'),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->twice()
            ->with('wal_sender_retry', (string) $sender->id)
            ->andReturn(['available_amount' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_recipient_retry', (string) $recipient->id)
            ->andReturn(['available_amount' => 25.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->andReturn(['id' => 'pay_p2p_retry']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;
        $payload = [
            'recipient_username' => 'recipient_retry',
            'amount' => 2500,
            'currency' => 'EUR',
            'device_token' => 'device-retry-1',
            'pin' => '1234',
        ];

        $first = $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'p2p-retry-1'])
            ->postJson('/api/wallet/transactions/p2p', $payload)
            ->assertCreated();

        $second = $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'p2p-retry-1'])
            ->postJson('/api/wallet/transactions/p2p', $payload)
            ->assertCreated();

        $this->assertSame($first->json('debit.id'), $second->json('debit.id'));
        $this->assertSame(2, FundingPartnerMovement::query()->count());
        $this->assertSame(2, LedgerEntry::query()->where('entry_type', LedgerEntryType::P2P_SEND->value)->orWhere('entry_type', LedgerEntryType::P2P_RECEIVE->value)->count());
    }

    public function test_p2p_completes_ledger_if_wallester_already_paid(): void
    {
        $this->seedWallesterSettings();

        $sender = $this->createActiveCustomer(['username' => 'sender_recover']);
        $recipient = $this->createActiveCustomer(['username' => 'recipient_recover']);

        $senderAccount = $this->createWallesterAccount($sender, IntegrationAccountStatus::ACTIVE, 'wal_sender_recover');
        $recipientAccount = $this->createWallesterAccount($recipient, IntegrationAccountStatus::ACTIVE, 'wal_recipient_recover');

        CustomerDevice::query()->create([
            'customer_id' => $sender->id,
            'device_name' => 'Phone',
            'device_token' => 'device-recover-1',
            'pin' => bcrypt('1234'),
        ]);

        $groupId = (string) Str::uuid();

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $senderAccount->getMorphClass(),
            'funding_account_id' => $senderAccount->id,
            'external_account_id' => $senderAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2500,
            'currency' => 'EUR',
            'transfer_group_id' => $groupId,
            'counterparty_external_account_id' => $recipientAccount->wallester_account_id,
            'partner_external_id' => 'pay_already',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:recover-1:out',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $recipientAccount->getMorphClass(),
            'funding_account_id' => $recipientAccount->id,
            'external_account_id' => $recipientAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => 2500,
            'currency' => 'EUR',
            'transfer_group_id' => $groupId,
            'counterparty_external_account_id' => $senderAccount->wallester_account_id,
            'partner_external_id' => 'pay_already',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'p2p:recover-1:in',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('accountTransfer');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $sender->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => 'recover-1'])
            ->postJson('/api/wallet/transactions/p2p', [
                'recipient_username' => 'recipient_recover',
                'amount' => 2500,
                'currency' => 'EUR',
                'device_token' => 'device-recover-1',
                'pin' => '1234',
            ])
            ->assertCreated()
            ->assertJsonPath('debit.amount', 2500);

        $this->assertSame(2, FundingPartnerMovement::query()->count());
        $this->assertDatabaseHas('stripe_transactions', [
            'customer_id' => $sender->id,
            'type' => 'p2p',
            'direction' => 'debit',
            'amount' => 2500,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $sender->id,
            'entry_type' => LedgerEntryType::P2P_SEND->value,
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
            ->withHeaders(['Idempotency-Key' => 'p2p-low-1'])
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

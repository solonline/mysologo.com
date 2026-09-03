<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\Wallester3dsService;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterService;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Domains\Wallet\Services\FundingPartnerMovementService;
use App\Domains\Wallet\Services\LedgerService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_card_persists_response_and_increments_cards_count(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, externalId: 'wal_acc_123');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['account_id'] === 'wal_acc_123'), null)
            ->andReturn([
                'id' => 'card_abc',
                'account_id' => 'wal_acc_123',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'masked_card_number' => '****1234',
                'expiry_date' => '2029-08-24T14:15:22Z',
                'is_disposable' => false,
                'security' => [
                    'contactless_enabled' => true,
                    'withdrawal_enabled' => false,
                ],
                'limits' => [
                    'daily_purchase' => 100000,
                    'daily_purchase_used' => 0,
                ],
                '3d_secure_settings' => [
                    'email' => 'user@example.com',
                    'password' => 'secret',
                ],
                'card_notification_settings' => [
                    'instant_spend_update_enabled' => true,
                ],
                'created_at' => '2026-07-01T10:00:00Z',
                'updated_at' => '2026-07-01T10:00:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCard([
            'account_id' => 'wal_acc_123',
            'type' => 'Virtual',
            'name' => 'Travel Card',
        ]);

        $this->assertInstanceOf(CustomerWallesterCard::class, $card);
        $this->assertSame('card_abc', $card->wallester_card_id);
        $this->assertSame('Virtual', $card->type);
        $this->assertSame('Active', $card->status);
        $this->assertSame('Travel Card', $card->name);
        $this->assertSame('****1234', $card->masked_card_number);
        $this->assertSame(['contactless_enabled' => true, 'withdrawal_enabled' => false], $card->security);
        $this->assertSame(['email' => 'user@example.com'], $card->three_d_secure_settings);
        $this->assertSame(['instant_spend_update_enabled' => true], $card->notification_settings);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_abc',
            'status' => 'Active',
        ]);
        $this->assertSame(1, $account->fresh()->cards_count);
    }

    public function test_create_card_for_customer_injects_oob_settings_and_activates_virtual_card(): void
    {
        $customer = $this->createRetailCustomer(['phone' => '351 912 345 678']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_acc_oob');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account, $customer): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'Virtual'
                    && $payload['name'] === 'Main Card'
                    && $payload['3d_secure_settings'] === [
                        'email' => (string) $customer->email,
                        'mobile' => '+351912345678',
                        'out_of_band_enabled' => true,
                        'out_of_band_id' => (string) $account->wallester_oob_id,
                    ]
                    && $payload['security']['internet_purchase_enabled'] === true
                    && $payload['security']['contactless_enabled'] === true
                    && $payload['security']['withdrawal_enabled'] === false;
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_oob',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Main Card',
            ]);

        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_oob', (string) $customer->id)
            ->andReturn([
                'id' => 'card_oob',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Main Card',
        ]);

        $this->assertSame('card_oob', $card->wallester_card_id);
        $this->assertSame('Active', $card->status);
        $this->assertNotNull($card->activated_at);
    }

    public function test_activate_virtual_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_activate');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_activate_1',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_activate_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_activate_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'masked_card_number' => '****9999',
                'activated_at' => '2026-07-01T12:30:00Z',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->activateVirtualCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
        $this->assertSame('****9999', $card->masked_card_number);
        $this->assertNotNull($card->activated_at);
    }

    public function test_activate_virtual_card_is_idempotent_when_already_active(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_active');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_active_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
            'activated_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->activateVirtualCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
    }

    public function test_create_card_for_customer_allows_additional_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_existing');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_second',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Second Card',
        ]);
        $gateway->shouldNotReceive('activateCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ]);

        $this->assertSame('card_second', $card->wallester_card_id);
    }

    public function test_create_card_for_customer_allows_new_card_when_existing_is_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_closed');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Old Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_new',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'New Card',
        ]);
        $gateway->shouldNotReceive('activateCard');

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'New Card',
        ]);

        $this->assertSame('card_new', $card->wallester_card_id);
    }

    public function test_create_additional_virtual_card_charges_fee_when_balance_covers_it(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_card',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_fee_from');
        $this->createWallesterAccount($this->createRetailCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_card');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_fee',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_fee_from', (string) $customer->id)
            ->andReturn(['available_amount' => 5.00, 'balance' => 5.00]);
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_paid_second',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Second Card',
        ]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['from_account_id'] === $account->wallester_account_id
                    && $payload['to_account_id'] === 'wal_fee_card'
                    && $payload['amount'] === 5.0
                    && $payload['description'] === 'Taxa de emissão de cartão virtual';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_card_fee']);
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_paid_second', (string) $customer->id)
            ->andReturn([
                'id' => 'card_paid_second',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Second Card',
            ]);

        $service = $this->makeService($gateway);

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ]);

        $this->assertSame('card_paid_second', $card->wallester_card_id);
        $this->assertSame('Active', $card->status);
    }

    public function test_create_additional_virtual_card_rejects_insufficient_balance(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
        ]);

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_fee_low');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_low',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Frozen Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_fee_low', (string) $customer->id)
            ->andReturn(['available_amount' => 4.99, 'balance' => 4.99]);
        $gateway->shouldNotReceive('createCard');
        $gateway->shouldNotReceive('accountTransfer');

        $service = $this->makeService($gateway);

        try {
            $service->createCardForCustomer($customer, [
                'type' => 'Virtual',
                'name' => 'Second Card',
            ]);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCode::INSUFFICIENT_BALANCE, $exception->reason);
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function test_create_card_for_customer_requires_active_wallester_account(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING, 'wal_pending');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Wallester account is not active.');

        $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Main Card',
        ]);
    }

    public function test_create_card_requires_known_wallester_account(): void
    {
        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = $this->makeService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester account [missing_acc] was not found.');

        $service->createCard([
            'account_id' => 'missing_acc',
            'type' => 'Virtual',
        ]);
    }

    public function test_block_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_block_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('blockCard')
            ->once()
            ->with('card_block_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_block_1',
                'type' => 'Virtual',
                'status' => 'Blocked',
                'name' => 'Main Card',
                'block_type' => 'BlockedByCardUser',
                'blocked_at' => '2026-09-03T10:00:00Z',
                'blocked_by' => (string) $customer->id,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->blockCardForCustomer($customer, $localCard);

        $this->assertSame('Blocked', $card->status);
        $this->assertSame('BlockedByCardUser', $card->block_type);
        $this->assertNotNull($card->blocked_at);
    }

    public function test_block_card_for_customer_is_idempotent_when_already_blocked(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_blocked');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_blocked_1',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Main Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');

        $service = $this->makeService($gateway);

        $card = $service->blockCardForCustomer($customer, $localCard);

        $this->assertSame('Blocked', $card->status);
        $this->assertSame('BlockedByCardUser', $card->block_type);
    }

    public function test_block_card_for_customer_rejects_non_active_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_block',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be blocked in its current status.');

        $service->blockCardForCustomer($customer, $localCard);
    }

    public function test_unblock_card_for_customer_updates_local_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_1',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Main Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('unblockCard')
            ->once()
            ->with('card_unblock_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_unblock_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
                'block_type' => null,
                'blocked_at' => null,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->unblockCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
        $this->assertNull($card->block_type);
        $this->assertNull($card->blocked_at);
    }

    public function test_unblock_card_for_customer_is_idempotent_when_already_active(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock_active');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_active',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');

        $service = $this->makeService($gateway);

        $card = $service->unblockCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
    }

    public function test_unblock_card_for_customer_rejects_non_blocked_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_unblock',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be unblocked in its current status.');

        $service->unblockCardForCustomer($customer, $localCard);
    }

    public function test_close_card_for_customer_updates_local_card_when_wallester_returns_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_close_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_close_1',
                'type' => 'Virtual',
                'status' => 'Closed',
                'name' => 'Main Card',
                'close_reason' => 'ClosedByCardholder',
                'closed_at' => '2026-09-03T10:00:00Z',
                'closed_by' => (string) $customer->id,
            ]);

        $service = $this->makeService($gateway);

        $card = $service->closeCardForCustomer($customer, $localCard);

        $this->assertSame('Closed', $card->status);
        $this->assertSame('ClosedByCardholder', $card->close_reason);
        $this->assertNotNull($card->closed_at);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_close_card_for_customer_does_not_persist_when_status_is_not_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close_no_confirm');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_no_confirm',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_close_no_confirm', (string) $customer->id)
            ->andReturn([
                'id' => 'card_close_no_confirm',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Main Card',
            ]);

        $service = $this->makeService($gateway);

        try {
            $service->closeCardForCustomer($customer, $localCard);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wallester card closing did not confirm a closed card.', $exception->getMessage());
        }

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Active',
            'close_reason' => null,
        ]);
    }

    public function test_close_card_for_customer_is_idempotent_when_already_closed(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_closed');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_closed_1',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Main Card',
            'close_reason' => 'ClosedByCardholder',
            'closed_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');

        $service = $this->makeService($gateway);

        $card = $service->closeCardForCustomer($customer, $localCard);

        $this->assertSame('Closed', $card->status);
        $this->assertSame('ClosedByCardholder', $card->close_reason);
        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_close_card_for_customer_rejects_created_card(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_close',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');

        $service = $this->makeService($gateway);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('This card cannot be closed in its current status.');

        $service->closeCardForCustomer($customer, $localCard);
    }

    private function makeService($gateway): WallesterService
    {
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        return new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
            app(WallesterSettingsService::class),
            app(WallesterFundingTransferDriver::class),
            app(FundingPartnerMovementService::class),
            app(LedgerService::class),
        );
    }
}

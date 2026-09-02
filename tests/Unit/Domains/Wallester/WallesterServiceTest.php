<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterAccountRepositoryInterface;
use App\Domains\Wallester\Services\Wallester3dsService;
use App\Domains\Wallester\Services\WallesterService;
use App\Exceptions\ApiException;
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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

        $card = $service->activateVirtualCardForCustomer($customer, $localCard);

        $this->assertSame('Active', $card->status);
    }

    public function test_create_card_for_customer_rejects_existing_card(): void
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
        $gateway->shouldNotReceive('createCard');

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Customer already has an active Wallester card.');

        $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ]);
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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'New Card',
        ]);

        $this->assertSame('card_new', $card->wallester_card_id);
    }

    public function test_create_card_for_customer_allows_additional_card_when_flag_is_set(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_additional');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_active',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')->once()->andReturn([
            'id' => 'card_extra',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Second Card',
        ]);
        $gateway->shouldNotReceive('activateCard');

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

        $card = $service->createCardForCustomer($customer, [
            'type' => 'Virtual',
            'name' => 'Second Card',
        ], allowAdditionalCards: true);

        $this->assertSame('card_extra', $card->wallester_card_id);
    }

    public function test_create_card_for_customer_requires_active_wallester_account(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING, 'wal_pending');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

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

        $service = new WallesterService(
            $gateway,
            app(WallesterAccountRepositoryInterface::class),
            app(Wallester3dsService::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallester account [missing_acc] was not found.');

        $service->createCard([
            'account_id' => 'missing_acc',
            'type' => 'Virtual',
        ]);
    }
}

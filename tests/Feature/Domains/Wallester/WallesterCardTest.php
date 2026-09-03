<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterCardSensitiveService;
use App\Models\CustomerDevice;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterCardTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_customer_can_create_and_auto_activate_virtual_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_api');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account, $customer): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'Virtual'
                    && $payload['name'] === 'Travel Card'
                    && $payload['3d_secure_settings']['out_of_band_enabled'] === true
                    && $payload['3d_secure_settings']['out_of_band_id'] === (string) $account->wallester_oob_id
                    && $payload['3d_secure_settings']['email'] === (string) $customer->email
                    && $payload['3d_secure_settings']['mobile'] === '+'.preg_replace('/\D+/', '', (string) $customer->phone)
                    && $payload['security']['internet_purchase_enabled'] === true;
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_api_1',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Travel Card',
                'masked_card_number' => '****5678',
                '3d_secure_settings' => [
                    'out_of_band_enabled' => true,
                    'out_of_band_id' => (string) $account->wallester_oob_id,
                ],
                'security' => [
                    'internet_purchase_enabled' => true,
                    'contactless_enabled' => true,
                    'withdrawal_enabled' => false,
                ],
            ]);

        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_api_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_api_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'masked_card_number' => '****5678',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Travel Card',
                ...$pin,
            ])
            ->assertCreated()
            ->assertJsonPath('data.wallester_card_id', 'card_api_1')
            ->assertJsonPath('data.type', 'Virtual')
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.name', 'Travel Card')
            ->assertJsonPath('data.masked_card_number', '****5678')
            ->assertJsonPath('data.activated_at', '2026-07-01T12:00:00+00:00');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_api_1',
            'name' => 'Travel Card',
            'status' => 'Active',
        ]);
        $this->assertSame(1, $account->fresh()->cards_count);
    }

    public function test_customer_can_show_wallester_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_show');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_show_api_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getCard')
            ->once()
            ->with('card_show_api_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_show_api_1',
                'account_id' => 'wal_card_show',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'masked_card_number' => '****5678',
                'currency_code' => 'EUR',
                'security' => [
                    'internet_purchase_enabled' => true,
                ],
                'limits' => [
                    'daily_purchase' => 1000,
                ],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/wallester/cards/{$localCard->id}")
            ->assertOk()
            ->assertJsonPath('data.id', 'card_show_api_1')
            ->assertJsonPath('data.name', 'Travel Card')
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonPath('data.limits.daily_purchase', 1000);
    }

    public function test_customer_can_reveal_sensitive_card_data_with_password(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sensitive_api');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sensitive_api',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $sensitiveService = Mockery::mock(WallesterCardSensitiveService::class);
        $sensitiveService->shouldReceive('revealForCustomer')
            ->once()
            ->with(
                Mockery::on(fn ($value) => $value->id === $customer->id),
                Mockery::on(fn ($value) => $value->id === $localCard->id),
                ['password' => 'password'],
            )
            ->andReturn([
                'card_number' => '4302631234561795',
                'cvv' => '987',
                'expiry_date' => '2029-01-31T23:59:59Z',
            ]);

        $this->app->instance(WallesterCardSensitiveService::class, $sensitiveService);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/sensitive", [
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('data.card_number', '4302631234561795')
            ->assertJsonPath('data.cvv', '987')
            ->assertJsonPath('data.expiry_date', '2029-01-31T23:59:59Z');
    }

    public function test_customer_can_reveal_sensitive_card_data_with_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sensitive_pin');

        CustomerDevice::query()->create([
            'customer_id' => $customer->id,
            'device_name' => 'Test Device',
            'device_token' => 'device-token-1',
            'pin' => bcrypt('1234'),
        ]);

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sensitive_pin',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $sensitiveService = Mockery::mock(WallesterCardSensitiveService::class);
        $sensitiveService->shouldReceive('revealForCustomer')
            ->once()
            ->with(
                Mockery::on(fn ($value) => $value->id === $customer->id),
                Mockery::on(fn ($value) => $value->id === $localCard->id),
                ['pin' => '1234', 'device_token' => 'device-token-1'],
            )
            ->andReturn([
                'card_number' => '4302631234561795',
                'cvv' => '987',
                'expiry_date' => '2029-01-31T23:59:59Z',
            ]);

        $this->app->instance(WallesterCardSensitiveService::class, $sensitiveService);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/sensitive", [
                'pin' => '1234',
                'device_token' => 'device-token-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.card_number', '4302631234561795');
    }

    public function test_sensitive_card_data_requires_password_or_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sensitive_missing');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sensitive_missing',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/sensitive", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'pin']);
    }

    public function test_sensitive_card_data_rejects_invalid_password(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sensitive_bad_pw');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sensitive_bad_pw',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/sensitive", [
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_credentials');
    }

    public function test_customer_cannot_show_another_customers_card(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_owner_show');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_owner_show',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Owner Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('getCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/wallester/cards/{$card->id}")
            ->assertForbidden();
    }

    public function test_customer_can_activate_virtual_card_via_endpoint(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_activate');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_manual_activate',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_manual_activate', (string) $customer->id)
            ->andReturn([
                'id' => 'card_manual_activate',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'activated_at' => '2026-07-01T13:00:00Z',
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.activated_at', '2026-07-01T13:00:00+00:00');
    }

    public function test_customer_cannot_activate_another_customers_card(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_owner');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_owner_only',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Owner Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('activateCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$card->id}/activate")
            ->assertForbidden();
    }

    public function test_customer_can_create_additional_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_dup');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Existing Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->andReturn([
                'id' => 'card_additional_1',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Another Card',
                'masked_card_number' => '****9999',
            ]);
        $gateway->shouldReceive('activateCard')
            ->once()
            ->andReturn([
                'id' => 'card_additional_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Another Card',
                'masked_card_number' => '****9999',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Another Card',
                ...$pin,
            ])
            ->assertCreated()
            ->assertJsonPath('data.wallester_card_id', 'card_additional_1')
            ->assertJsonPath('data.status', 'Active');
    }

    public function test_customer_pays_fee_when_creating_additional_virtual_card(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
            'wallester.eur.platform_fee_account_id' => 'wal_fee_api',
        ]);

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_paid');
        $this->createWallesterAccount($this->createActiveCustomer(), IntegrationAccountStatus::ACTIVE, 'wal_fee_api');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_paid',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Existing Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_card_paid', (string) $customer->id)
            ->andReturn(['available_amount' => 10.00, 'balance' => 10.00]);
        $gateway->shouldReceive('createCard')
            ->once()
            ->andReturn([
                'id' => 'card_paid_api',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Paid Card',
            ]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['from_account_id'] === $account->wallester_account_id
                    && $payload['to_account_id'] === 'wal_fee_api'
                    && $payload['amount'] === 5.0;
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_card_fee_api']);
        $gateway->shouldReceive('activateCard')
            ->once()
            ->andReturn([
                'id' => 'card_paid_api',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Paid Card',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Paid Card',
                ...$pin,
            ])
            ->assertCreated()
            ->assertJsonPath('data.wallester_card_id', 'card_paid_api');
    }

    public function test_customer_cannot_create_additional_virtual_card_without_balance(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '500',
        ]);

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_broke');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_existing_broke',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Existing Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_card_broke', (string) $customer->id)
            ->andReturn(['available_amount' => 1.00, 'balance' => 1.00]);
        $gateway->shouldNotReceive('createCard');
        $gateway->shouldNotReceive('accountTransfer');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Paid Card',
                ...$pin,
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'insufficient_balance');
    }

    public function test_create_card_allows_replacement_when_existing_card_is_closed(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_closed');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_closed_1',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Old Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->andReturn([
                'id' => 'card_replacement_1',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'New Card',
                'masked_card_number' => '****1234',
            ]);
        $gateway->shouldReceive('activateCard')
            ->once()
            ->andReturn([
                'id' => 'card_replacement_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'New Card',
                'masked_card_number' => '****1234',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'New Card',
                ...$pin,
            ])
            ->assertCreated()
            ->assertJsonPath('data.wallester_card_id', 'card_replacement_1');
    }

    public function test_customer_can_list_wallester_cards(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_list');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_list_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
            'masked_card_number' => '****5678',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/cards')
            ->assertOk()
            ->assertJsonPath('data.0.id', $localCard->id)
            ->assertJsonPath('data.0.name', 'Travel Card')
            ->assertJsonPath('data.0.masked_card_number', '****5678');
    }

    public function test_customer_list_includes_blocked_cards(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_list_blocked');

        $blockedCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_list_blocked',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Frozen Card',
            'masked_card_number' => '****1111',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => '2026-09-03T10:00:00Z',
        ]);

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_list_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Closed Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/cards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blockedCard->id)
            ->assertJsonPath('data.0.status', 'Blocked')
            ->assertJsonPath('data.0.block_type', 'BlockedByCardUser');
    }

    public function test_customer_can_block_active_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_to_block',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('blockCard')
            ->once()
            ->with('card_to_block', (string) $customer->id)
            ->andReturn([
                'id' => 'card_to_block',
                'type' => 'Virtual',
                'status' => 'Blocked',
                'name' => 'Travel Card',
                'block_type' => 'BlockedByCardUser',
                'blocked_at' => '2026-09-03T10:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $response = $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/block", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Blocked')
            ->assertJsonPath('data.block_type', 'BlockedByCardUser');

        $this->assertNotEmpty($response->json('data.blocked_at'));

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Blocked',
            'block_type' => 'BlockedByCardUser',
        ]);
    }

    public function test_customer_cannot_block_another_customers_card(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_owner_block');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_owner_block',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Owner Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($intruder, 'device-intruder-block');

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$card->id}/block", $pin)
            ->assertForbidden();
    }

    public function test_customer_cannot_block_created_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_block');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_block',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/block", $pin)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'card_not_blockable');
    }

    public function test_block_card_is_idempotent_when_already_blocked(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_already_blocked');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_already_blocked',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Travel Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => '2026-09-03T10:00:00Z',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/block", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Blocked')
            ->assertJsonPath('data.block_type', 'BlockedByCardUser');
    }

    public function test_create_card_validates_required_fields(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name', 'pin', 'device_token']);
    }

    public function test_create_card_requires_active_wallester_account(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING);
        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Travel Card',
                ...$pin,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Wallester account is not active.');
    }

    public function test_create_card_rejects_invalid_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_bad_pin');
        $this->attachDevicePin($customer);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Travel Card',
                'pin' => '0000',
                'device_token' => 'device-card-1',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_pin');
    }

    public function test_block_card_rejects_invalid_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_block_bad_pin');
        $this->attachDevicePin($customer);

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_block_bad_pin',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('blockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/block", [
                'pin' => '0000',
                'device_token' => 'device-card-1',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_pin');
    }

    public function test_block_card_requires_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_block_no_pin');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_block_no_pin',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/block")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin', 'device_token']);
    }

    public function test_customer_can_unblock_blocked_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_to_unblock',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Travel Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => '2026-09-03T10:00:00Z',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('unblockCard')
            ->once()
            ->with('card_to_unblock', (string) $customer->id)
            ->andReturn([
                'id' => 'card_to_unblock',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
                'block_type' => null,
                'blocked_at' => null,
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/unblock", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Active');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Active',
        ]);
    }

    public function test_customer_cannot_unblock_another_customers_card(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_owner_unblock');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_owner_unblock',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Owner Card',
            'block_type' => 'BlockedByCardUser',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($intruder, 'device-intruder-unblock');

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$card->id}/unblock", $pin)
            ->assertForbidden();
    }

    public function test_customer_cannot_unblock_created_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_unblock');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_unblock',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/unblock", $pin)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'card_not_unblockable');
    }

    public function test_unblock_card_is_idempotent_when_already_active(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_already_active');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_already_active',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/unblock", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Active');
    }

    public function test_unblock_card_rejects_invalid_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock_bad_pin');
        $this->attachDevicePin($customer);

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_bad_pin',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Travel Card',
            'block_type' => 'BlockedByCardUser',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('unblockCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/unblock", [
                'pin' => '0000',
                'device_token' => 'device-card-1',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_pin');
    }

    public function test_unblock_card_requires_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_unblock_no_pin');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_unblock_no_pin',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Travel Card',
            'block_type' => 'BlockedByCardUser',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/unblock")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin', 'device_token']);
    }

    public function test_customer_can_close_active_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_to_close',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_to_close', (string) $customer->id)
            ->andReturn([
                'id' => 'card_to_close',
                'type' => 'Virtual',
                'status' => 'Closed',
                'name' => 'Travel Card',
                'close_reason' => 'ClosedByCardholder',
                'closed_at' => '2026-09-03T10:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $response = $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Closed')
            ->assertJsonPath('data.close_reason', 'ClosedByCardholder');

        $this->assertNotEmpty($response->json('data.closed_at'));

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'wallester_card_id' => 'card_to_close',
            'status' => 'Closed',
            'close_reason' => 'ClosedByCardholder',
        ]);

        $this->withToken($token)
            ->getJson('/api/wallester/cards')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_customer_can_close_blocked_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_close_blocked');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_blocked',
            'type' => 'Virtual',
            'status' => 'Blocked',
            'name' => 'Travel Card',
            'block_type' => 'BlockedByCardUser',
            'blocked_at' => '2026-09-03T10:00:00Z',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('closeCard')
            ->once()
            ->with('card_close_blocked', (string) $customer->id)
            ->andReturn([
                'id' => 'card_close_blocked',
                'type' => 'Virtual',
                'status' => 'Closed',
                'name' => 'Travel Card',
                'close_reason' => 'ClosedByCardholder',
                'closed_at' => '2026-09-03T11:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Closed');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_customer_cannot_close_another_customers_card(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_owner_close');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_owner_close',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Owner Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($intruder, 'device-intruder-close');

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$card->id}/close", $pin)
            ->assertForbidden();

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $card->id,
            'status' => 'Active',
        ]);
    }

    public function test_customer_cannot_close_created_card(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_created_close');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_created_close',
            'type' => 'Virtual',
            'status' => 'Created',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close", $pin)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'card_not_closable');
    }

    public function test_close_card_is_idempotent_when_already_closed(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_already_closed');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_already_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Travel Card',
            'close_reason' => 'ClosedByCardholder',
            'closed_at' => '2026-09-03T10:00:00Z',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;
        $pin = $this->attachDevicePin($customer);

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close", $pin)
            ->assertOk()
            ->assertJsonPath('data.status', 'Closed')
            ->assertJsonPath('data.close_reason', 'ClosedByCardholder');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $localCard->id,
            'status' => 'Closed',
        ]);
    }

    public function test_close_card_rejects_invalid_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close_bad_pin');
        $this->attachDevicePin($customer);

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_bad_pin',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('closeCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close", [
                'pin' => '0000',
                'device_token' => 'device-card-1',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_pin');
    }

    public function test_close_card_requires_pin(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_close_no_pin');

        $localCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_close_no_pin',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$localCard->id}/close")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin', 'device_token']);
    }

    public function test_issuance_quote_is_free_for_first_card(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '45',
        ]);

        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_quote_first');

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/cards/issuance-quote')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.virtual.fee_cents', 45)
            ->assertJsonPath('data.virtual.will_charge', false)
            ->assertJsonPath('data.physical.fee_cents', 0)
            ->assertJsonPath('data.physical.will_charge', false);
    }

    public function test_issuance_quote_is_free_when_existing_card_is_closed(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '45',
        ]);

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_quote_closed');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_quote_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Old Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/cards/issuance-quote')
            ->assertOk()
            ->assertJsonPath('data.virtual.fee_cents', 45)
            ->assertJsonPath('data.virtual.will_charge', false);
    }

    public function test_issuance_quote_charges_additional_virtual_card(): void
    {
        $this->seedWallesterSettings([
            'wallester.virtual_card_value' => '45',
        ]);

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_quote_extra');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_quote_open',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Open Card',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/cards/issuance-quote')
            ->assertOk()
            ->assertJsonPath('data.virtual.fee_cents', 45)
            ->assertJsonPath('data.virtual.will_charge', true)
            ->assertJsonPath('data.physical.fee_cents', 0)
            ->assertJsonPath('data.physical.will_charge', false);
    }

    /**
     * @return array{pin: string, device_token: string}
     */
    private function attachDevicePin($customer, string $deviceToken = 'device-card-1', string $pin = '1234'): array
    {
        CustomerDevice::query()->create([
            'customer_id' => $customer->id,
            'device_name' => 'Test Device',
            'device_token' => $deviceToken,
            'pin' => bcrypt($pin),
        ]);

        return [
            'pin' => $pin,
            'device_token' => $deviceToken,
        ];
    }

    private function createActiveCustomer()
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }
}

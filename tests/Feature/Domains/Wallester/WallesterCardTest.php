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
use Tests\TestCase;

class WallesterCardTest extends TestCase
{
    use InteractsWithOnboarding;
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

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'Virtual'
                    && $payload['name'] === 'Travel Card'
                    && $payload['3d_secure_settings']['out_of_band_enabled'] === true
                    && $payload['3d_secure_settings']['out_of_band_id'] === (string) $account->wallester_oob_id
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

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Travel Card',
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

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
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

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
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

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
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

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
        $gateway->shouldNotReceive('activateCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/cards/{$card->id}/activate")
            ->assertForbidden();
    }

    public function test_create_card_validates_required_fields(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name']);
    }

    public function test_create_card_requires_active_wallester_account(): void
    {
        $customer = $this->createActiveCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/wallester/cards', [
                'type' => 'Virtual',
                'name' => 'Travel Card',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Wallester account is not active.');
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

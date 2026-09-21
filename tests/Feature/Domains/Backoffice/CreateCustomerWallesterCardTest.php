<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Models\CustomerWallesterCard;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class CreateCustomerWallesterCardTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Permission::findOrCreate('customers.edit', 'web');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_staff_can_create_virtual_card_for_customer(): void
    {
        $user = $this->staffWithPermissions(['customers.view', 'customers.edit']);

        $customer = $this->createRetailCustomer([
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'phone' => '+351912345678',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_bo_card');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($account, $customer): bool {
                return $payload['account_id'] === $account->wallester_account_id
                    && $payload['type'] === 'Virtual'
                    && $payload['name'] === 'Mario Rossi'
                    && $payload['3d_secure_settings'] === [
                        'email' => (string) $customer->email,
                        'mobile' => '+351912345678',
                        'out_of_band_enabled' => true,
                        'out_of_band_id' => (string) $account->wallester_oob_id,
                    ];
            }), (string) $customer->id)
            ->andReturn([
                'id' => 'card_bo_1',
                'type' => 'Virtual',
                'status' => 'Created',
                'name' => 'Mario Rossi',
                'masked_card_number' => '****4242',
            ]);
        $this->expectWallesterGetCard($gateway, 'card_bo_1');
        $gateway->shouldReceive('activateCard')
            ->once()
            ->with('card_bo_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_bo_1',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Mario Rossi',
                'masked_card_number' => '****4242',
                'activated_at' => '2026-07-01T12:00:00Z',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.wallester-cards.store', $customer))
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('success', 'Cartão virtual criado com sucesso.');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_bo_1',
            'name' => 'Mario Rossi',
            'status' => 'Active',
        ]);
    }

    public function test_staff_can_create_additional_card_when_customer_already_has_one(): void
    {
        $user = $this->staffWithPermissions(['customers.view', 'customers.edit']);

        $customer = $this->createRetailCustomer(['name' => 'Second Card']);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_bo_extra');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_bo_existing',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Existing Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('createCard')
            ->once()
            ->andReturn([
                'id' => 'card_bo_2',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Second Card',
            ]);
        $gateway->shouldNotReceive('activateCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.wallester-cards.store', $customer))
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'wallester_card_id' => 'card_bo_2',
            'customer_wallester_account_id' => $account->id,
        ]);
    }

    public function test_staff_cannot_create_card_when_wallester_account_is_not_active(): void
    {
        $user = $this->staffWithPermissions(['customers.view', 'customers.edit']);

        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING, 'wal_bo_pending');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('createCard');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.wallester-cards.store', $customer))
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error', 'Wallester account is not active.');
    }

    public function test_staff_without_edit_permission_cannot_create_card(): void
    {
        $user = $this->staffWithPermissions(['customers.view']);

        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_bo_forbidden');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.wallester-cards.store', $customer))
            ->assertForbidden();
    }

    /** @param  list<string>  $permissions */
    private function staffWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            collect($permissions)->map(fn (string $permission) => Permission::findByName($permission, 'web'))->all(),
        );

        return $user;
    }
}

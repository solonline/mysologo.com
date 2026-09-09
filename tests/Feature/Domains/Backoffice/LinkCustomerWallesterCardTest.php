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

class LinkCustomerWallesterCardTest extends TestCase
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

    public function test_staff_can_link_card_to_another_customer_by_id(): void
    {
        [$user, $owner, $target, $card, $sourceAccount, $targetAccount] = $this->prepareLinkableCard();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('linkCardToAccount')
            ->once()
            ->with('card_bo_link_1', 'wal_bo_link_to', (string) $owner->id)
            ->andReturn([
                'id' => 'card_bo_link_1',
                'account_id' => 'wal_bo_link_to',
                'type' => 'Virtual',
                'status' => 'Active',
                'name' => 'Travel Card',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => (string) $target->id,
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('success', 'Cartão transferido para o cliente de destino.');

        $this->assertDatabaseHas('customer_wallester_cards', [
            'id' => $card->id,
            'customer_wallester_account_id' => $targetAccount->id,
            'wallester_card_id' => 'card_bo_link_1',
        ]);
        $this->assertSame(0, $sourceAccount->fresh()->cards_count);
        $this->assertSame(1, $targetAccount->fresh()->cards_count);
    }

    public function test_staff_can_link_card_to_another_customer_by_email(): void
    {
        [$user, $owner, $target, $card] = $this->prepareLinkableCard([
            'target' => ['email' => 'destino@example.com'],
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('linkCardToAccount')
            ->once()
            ->andReturn([
                'id' => 'card_bo_link_1',
                'account_id' => 'wal_bo_link_to',
                'status' => 'Active',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => 'destino@example.com',
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('success');
    }

    public function test_staff_can_link_card_to_another_customer_by_username(): void
    {
        [$user, $owner, $target, $card] = $this->prepareLinkableCard([
            'target' => ['username' => 'cliente-destino'],
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('linkCardToAccount')
            ->once()
            ->andReturn([
                'id' => 'card_bo_link_1',
                'account_id' => 'wal_bo_link_to',
                'status' => 'Active',
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => '@cliente-destino',
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('success');
    }

    public function test_staff_cannot_link_card_to_the_same_customer(): void
    {
        [$user, $owner, , $card] = $this->prepareLinkableCard();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => (string) $owner->id,
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('error', 'Cannot link a card to the same customer.');
    }

    public function test_staff_cannot_link_closed_card(): void
    {
        [$user, $owner, $target, $card] = $this->prepareLinkableCard([
            'card' => ['status' => 'Closed'],
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => (string) $target->id,
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('error', 'This card cannot be linked in its current status.');
    }

    public function test_staff_cannot_link_card_when_target_account_is_not_active(): void
    {
        [$user, $owner, $target, $card] = $this->prepareLinkableCard([
            'target_account_status' => IntegrationAccountStatus::PENDING,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => (string) $target->id,
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('error', 'Wallester account not found or not active for the target customer.');
    }

    public function test_staff_cannot_link_card_that_does_not_belong_to_customer(): void
    {
        [$user, $owner, $target, $card] = $this->prepareLinkableCard();
        $other = $this->createRetailCustomer();
        $this->createWallesterAccount($other, IntegrationAccountStatus::ACTIVE, 'wal_bo_link_other');

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $other))
            ->post(route('dashboard.customers.wallester-cards.link', $other), [
                'card_id' => $card->id,
                'target_customer' => (string) $target->id,
            ])
            ->assertRedirect(route('dashboard.customers.show', $other))
            ->assertSessionHas('error', 'Este cartão não pertence a este cliente.');
    }

    public function test_staff_cannot_link_card_when_target_customer_is_missing(): void
    {
        [$user, $owner, , $card] = $this->prepareLinkableCard();

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('linkCardToAccount');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $owner))
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => 'nao-existe@example.com',
            ])
            ->assertRedirect(route('dashboard.customers.show', $owner))
            ->assertSessionHas('error', 'Cliente de destino não encontrado.');
    }

    public function test_staff_without_edit_permission_cannot_link_card(): void
    {
        $user = $this->staffWithPermissions(['customers.view']);
        [, $owner, $target, $card] = $this->prepareLinkableCard(['user' => $user]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.wallester-cards.link', $owner), [
                'card_id' => $card->id,
                'target_customer' => (string) $target->id,
            ])
            ->assertForbidden();
    }

    public function test_staff_can_search_usernames_for_card_transfer(): void
    {
        $user = $this->staffWithPermissions(['customers.view', 'customers.edit']);
        $owner = $this->createRetailCustomer(['username' => 'dono_cartao']);
        $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_search_owner');

        $match = $this->createRetailCustomer([
            'username' => 'ana_destino',
            'name' => 'Ana Destino',
        ]);
        $this->createWallesterAccount($match, IntegrationAccountStatus::ACTIVE, 'wal_search_match');

        $pending = $this->createRetailCustomer(['username' => 'ana_pendente']);
        $this->createWallesterAccount($pending, IntegrationAccountStatus::PENDING, 'wal_search_pending');

        $other = $this->createRetailCustomer(['username' => 'bruno_destino']);
        $this->createWallesterAccount($other, IntegrationAccountStatus::ACTIVE, 'wal_search_other');

        $this->actingAs($user, 'web')
            ->getJson(route('dashboard.customers.username-search', [
                'q' => 'ana',
                'exclude' => $owner->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'ana_destino')
            ->assertJsonPath('data.0.name', 'Ana Destino')
            ->assertJsonMissing(['username' => 'dono_cartao'])
            ->assertJsonMissing(['username' => 'ana_pendente'])
            ->assertJsonMissing(['username' => 'bruno_destino']);
    }

    public function test_username_search_returns_empty_for_short_query(): void
    {
        $user = $this->staffWithPermissions(['customers.view']);
        $this->createRetailCustomer(['username' => 'ab_user']);

        $this->actingAs($user, 'web')
            ->getJson(route('dashboard.customers.username-search', ['q' => 'a']))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_staff_without_view_permission_cannot_search_usernames(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->getJson(route('dashboard.customers.username-search', ['q' => 'ana']))
            ->assertForbidden();
    }

    /**
     * @param  array{target?: array<string, mixed>, card?: array<string, mixed>, target_account_status?: IntegrationAccountStatus, user?: User}  $options
     * @return array{0: User, 1: \App\Models\Customer, 2: \App\Models\Customer, 3: CustomerWallesterCard, 4: \App\Models\CustomerWallesterAccount, 5: \App\Models\CustomerWallesterAccount}
     */
    private function prepareLinkableCard(array $options = []): array
    {
        $user = $options['user'] ?? $this->staffWithPermissions(['customers.view', 'customers.edit']);
        $owner = $this->createRetailCustomer(['name' => 'Dono Cartao']);
        $target = $this->createRetailCustomer($options['target'] ?? []);
        $sourceAccount = $this->createWallesterAccount($owner, IntegrationAccountStatus::ACTIVE, 'wal_bo_link_from', [
            'cards_count' => 1,
        ]);
        $targetAccount = $this->createWallesterAccount(
            $target,
            $options['target_account_status'] ?? IntegrationAccountStatus::ACTIVE,
            'wal_bo_link_to',
        );

        $card = CustomerWallesterCard::query()->create(array_merge([
            'customer_wallester_account_id' => $sourceAccount->id,
            'wallester_card_id' => 'card_bo_link_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ], $options['card'] ?? []));

        return [$user, $owner, $target, $card, $sourceAccount, $targetAccount];
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

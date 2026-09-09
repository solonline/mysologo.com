<?php

namespace Tests\Feature\Domains\Customer;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerFavoritesTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_lists_favorite_customers_most_recent_first(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_one']);
        $first = $this->createActiveCustomer(['username' => 'fav_first', 'name' => 'Ana']);
        $second = $this->createActiveCustomer(['username' => 'fav_second', 'name' => 'Bruno']);
        $inactive = $this->createRetailCustomer([
            'username' => 'fav_inactive',
            'status' => CustomerLifecycleStatus::BLOCKED,
            'email_verified_at' => now(),
            'doc_status' => DocumentStatus::APPROVED->value,
        ]);
        $unapproved = $this->createActiveCustomer([
            'username' => 'fav_pending_doc',
            'doc_status' => DocumentStatus::PENDING->value,
        ]);

        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $first->id,
            'created_at' => now()->subMinute(),
        ]);
        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $second->id,
        ]);
        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $inactive->id,
        ]);
        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $unapproved->id,
        ]);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->getJson('/api/customer/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.username', 'fav_second')
            ->assertJsonPath('data.0.name', 'Bruno')
            ->assertJsonPath('data.1.username', 'fav_first')
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'username', 'avatar_url'],
                ],
            ]);
    }

    public function test_favorites_requires_authentication(): void
    {
        $this->getJson('/api/customer/favorites')->assertUnauthorized();
        $this->postJson('/api/customer/favorites', ['username' => 'someone'])->assertUnauthorized();
        $this->deleteJson('/api/customer/favorites/someone')->assertUnauthorized();
    }

    public function test_adds_favorite_customer_by_username(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_add']);
        $favorite = $this->createActiveCustomer(['username' => 'fav_target', 'name' => 'Carla']);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->postJson('/api/customer/favorites', ['username' => 'fav_target'])
            ->assertCreated()
            ->assertJsonPath('username', 'fav_target')
            ->assertJsonPath('name', 'Carla')
            ->assertJsonPath('id', $favorite->id);

        $this->assertDatabaseHas('customer_favorites', [
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);
    }

    public function test_adding_favorite_is_idempotent(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_idempotent']);
        $favorite = $this->createActiveCustomer(['username' => 'fav_once']);

        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/customer/favorites', ['username' => 'fav_once'])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/customer/favorites', ['username' => 'fav_once'])
            ->assertCreated();

        $this->assertSame(1, CustomerFavorite::query()
            ->where('customer_id', $owner->id)
            ->where('favorite_customer_id', $favorite->id)
            ->count());
    }

    public function test_cannot_favorite_self(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_self']);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->postJson('/api/customer/favorites', ['username' => 'owner_self'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'favorite_self');
    }

    public function test_cannot_favorite_unknown_or_ineligible_customer(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_lookup']);
        $this->createRetailCustomer([
            'username' => 'fav_blocked',
            'status' => CustomerLifecycleStatus::BLOCKED,
            'email_verified_at' => now(),
            'doc_status' => DocumentStatus::APPROVED->value,
        ]);

        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/customer/favorites', ['username' => 'does_not_exist'])
            ->assertNotFound()
            ->assertJsonPath('reason', 'customer_not_found');

        $this->withToken($token)
            ->postJson('/api/customer/favorites', ['username' => 'fav_blocked'])
            ->assertNotFound()
            ->assertJsonPath('reason', 'customer_not_found');
    }

    public function test_removes_favorite_customer_by_username(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_remove']);
        $favorite = $this->createActiveCustomer(['username' => 'fav_remove']);

        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->deleteJson('/api/customer/favorites/fav_remove')
            ->assertOk()
            ->assertJsonPath('message', 'Favorite removed successfully');

        $this->assertDatabaseMissing('customer_favorites', [
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);
    }

    public function test_removing_favorite_is_idempotent_and_allows_inactive_targets(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_cleanup']);
        $inactive = $this->createRetailCustomer([
            'username' => 'fav_gone',
            'status' => CustomerLifecycleStatus::BLOCKED,
            'email_verified_at' => now(),
            'doc_status' => DocumentStatus::APPROVED->value,
        ]);

        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $inactive->id,
        ]);

        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/customer/favorites/fav_gone')
            ->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/customer/favorites/fav_gone')
            ->assertOk();

        $this->assertDatabaseMissing('customer_favorites', [
            'customer_id' => $owner->id,
            'favorite_customer_id' => $inactive->id,
        ]);
    }

    private function createActiveCustomer(array $overrides = []): Customer
    {
        return $this->createRetailCustomer(array_merge([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => DocumentStatus::APPROVED->value,
        ], $overrides));
    }
}

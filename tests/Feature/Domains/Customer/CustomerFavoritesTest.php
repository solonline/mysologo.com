<?php

namespace Tests\Feature\Domains\Customer;

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
            'doc_status' => 'approved',
        ]);
        $unapproved = $this->createActiveCustomer([
            'username' => 'fav_pending_doc',
            'doc_status' => 'pending',
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

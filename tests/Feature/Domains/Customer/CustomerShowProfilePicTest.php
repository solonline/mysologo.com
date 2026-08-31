<?php

namespace Tests\Feature\Domains\Customer;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerShowProfilePicTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_p2p_lookup_returns_avatar_when_recipient_allows_it(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_one']);
        $recipient = $this->createActiveCustomer([
            'username' => 'alice_pay',
            'name' => 'Alice',
            'avatar' => 'avatars/alice.png',
            'show_profile_pic' => true,
        ]);

        $this->withToken($sender->createToken('test')->plainTextToken)
            ->getJson('/api/customer/find/alice_pay')
            ->assertOk()
            ->assertJsonPath('username', 'alice_pay')
            ->assertJsonPath('avatar_url', asset('storage/avatars/alice.png'));
    }

    public function test_p2p_lookup_hides_avatar_when_recipient_disables_profile_pic(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_two']);
        $this->createActiveCustomer([
            'username' => 'bob_pay',
            'name' => 'Bob',
            'avatar' => 'avatars/bob.png',
            'show_profile_pic' => false,
        ]);

        $this->withToken($sender->createToken('test')->plainTextToken)
            ->getJson('/api/customer/find/bob_pay')
            ->assertOk()
            ->assertJsonPath('username', 'bob_pay')
            ->assertJsonPath('name', 'Bob')
            ->assertJsonPath('avatar_url', null);
    }

    public function test_favorites_hide_avatar_when_favorite_disables_profile_pic(): void
    {
        $owner = $this->createActiveCustomer(['username' => 'owner_fav']);
        $favorite = $this->createActiveCustomer([
            'username' => 'hidden_pic',
            'name' => 'Carla',
            'avatar' => 'avatars/carla.png',
            'show_profile_pic' => false,
        ]);

        CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'favorite_customer_id' => $favorite->id,
        ]);

        $this->withToken($owner->createToken('test')->plainTextToken)
            ->getJson('/api/customer/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'hidden_pic')
            ->assertJsonPath('data.0.avatar_url', null);
    }

    public function test_customer_can_toggle_show_profile_pic(): void
    {
        $customer = $this->createActiveCustomer([
            'username' => 'toggle_pic',
            'show_profile_pic' => false,
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/customer/update', ['show_profile_pic' => true])
            ->assertOk()
            ->assertJsonPath('data.show_profile_pic', true);

        $this->assertTrue($customer->fresh()->show_profile_pic);
    }

    public function test_new_customers_hide_profile_pic_by_default(): void
    {
        $sender = $this->createActiveCustomer(['username' => 'sender_default']);
        $recipient = $this->createActiveCustomer([
            'username' => 'default_hidden',
            'name' => 'Diana',
            'avatar' => 'avatars/diana.png',
        ]);

        $this->assertFalse($recipient->show_profile_pic);

        $this->withToken($sender->createToken('test')->plainTextToken)
            ->getJson('/api/customer/find/default_hidden')
            ->assertOk()
            ->assertJsonPath('avatar_url', null);
    }

    public function test_own_profile_always_includes_avatar_and_flag(): void
    {
        $customer = $this->createActiveCustomer([
            'username' => 'me_pic',
            'avatar' => 'avatars/me.png',
            'show_profile_pic' => false,
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->getJson('/api/customer/me')
            ->assertOk()
            ->assertJsonPath('data.avatar', 'avatars/me.png')
            ->assertJsonPath('data.show_profile_pic', false);
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

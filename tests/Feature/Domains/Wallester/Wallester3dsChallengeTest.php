<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Enums\Wallester3dsChallengeStatus;
use App\Domains\Wallester\Services\Wallester3dsService;
use App\Models\CustomerWallesterCard;
use App\Models\Wallester3dsChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class Wallester3dsChallengeTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_customer_can_list_pending_challenges(): void
    {
        $customer = $this->createActiveCustomer();
        $challenge = $this->createChallengeForCustomer($customer);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/wallester/3ds-challenges/pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $challenge->id)
            ->assertJsonPath('data.0.status', Wallester3dsChallengeStatus::Pending->value);
    }

    public function test_customer_cannot_resolve_another_customers_challenge(): void
    {
        $owner = $this->createActiveCustomer();
        $intruder = $this->createActiveCustomer();
        $challenge = $this->createChallengeForCustomer($owner);
        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/3ds-challenges/{$challenge->id}/approve")
            ->assertStatus(422);
    }

    public function test_out_of_band_settings_use_wallester_account_uuid(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer);

        $settings = app(Wallester3dsService::class)->outOfBandSettingsFor($account);

        $this->assertTrue($settings['out_of_band_enabled']);
        $this->assertSame($account->wallester_oob_id, $settings['out_of_band_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $account->wallester_oob_id,
        );
    }

    private function createActiveCustomer()
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
    }

    private function createChallengeForCustomer($customer): Wallester3dsChallenge
    {
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_'.$customer->id);
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_'.$customer->id,
            'type' => 'Virtual',
            'status' => 'Active',
        ]);

        return Wallester3dsChallenge::query()->create([
            'wallester_request_id' => 'req_'.$customer->id,
            'wallester_x_request_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'customer_id' => $customer->id,
            'customer_wallester_card_id' => $card->id,
            'wallester_card_id' => $card->wallester_card_id,
            'status' => Wallester3dsChallengeStatus::Pending,
            'merchant' => ['name' => 'Shop'],
            'transaction' => ['amount' => '10.00', 'currency' => 'EUR'],
            'card' => ['id' => $card->wallester_card_id, 'last_four_digits' => '1111'],
            'expires_at' => now()->addMinutes(5),
        ]);
    }
}

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
        $customer = $this->createRetailCustomer([
            'email' => 'mario@example.com',
            'phone' => '+351 912 345 678',
        ]);
        $account = $this->createWallesterAccount($customer);

        $settings = app(Wallester3dsService::class)->outOfBandSettingsFor($account, $customer);

        $this->assertTrue($settings['out_of_band_enabled']);
        $this->assertSame($account->wallester_oob_id, $settings['out_of_band_id']);
        $this->assertSame('mario@example.com', $settings['email']);
        $this->assertSame('+351912345678', $settings['mobile']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $account->wallester_oob_id,
        );
    }

    public function test_out_of_band_mobile_normalizes_digits_only_and_international_prefix(): void
    {
        $digitsOnly = $this->createRetailCustomer(['phone' => '351912345678']);
        $with00 = $this->createRetailCustomer(['phone' => '00351 912 345 678']);

        $this->assertSame(
            '+351912345678',
            app(Wallester3dsService::class)->outOfBandSettingsFor(
                $this->createWallesterAccount($digitsOnly),
                $digitsOnly,
            )['mobile'],
        );
        $this->assertSame(
            '+351912345678',
            app(Wallester3dsService::class)->outOfBandSettingsFor(
                $this->createWallesterAccount($with00),
                $with00,
            )['mobile'],
        );
    }

    public function test_out_of_band_settings_require_a_valid_phone(): void
    {
        $customer = $this->createRetailCustomer(['phone' => '']);
        $account = $this->createWallesterAccount($customer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer phone must be a valid number for Wallester 3DS');

        app(Wallester3dsService::class)->outOfBandSettingsFor($account, $customer);
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

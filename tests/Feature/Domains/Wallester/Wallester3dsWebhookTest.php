<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Enums\Wallester3dsChallengeStatus;
use App\Models\CustomerWallesterCard;
use App\Models\Setting;
use App\Models\Wallester3dsChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class Wallester3dsWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallester3dsWebhookSettings();
    }

    private function seedWallester3dsWebhookSettings(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'wallester.3ds_webhook_username'],
            ['value' => 'wallester-3ds', 'type' => 'secret', 'label' => 'Wallester 3DS Webhook Username'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'wallester.3ds_webhook_password'],
            ['value' => 'secret-3ds', 'type' => 'secret', 'label' => 'Wallester 3DS Webhook Password'],
        );
    }

    public function test_initiate_action_creates_challenge_and_notifies_customer(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_acc_3ds');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_3ds_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $xRequestId = '11111111-1111-1111-1111-111111111111';

        $response = $this->postJson('/api/webhooks/wallester/3ds/initiate-action-event', [
            'event' => 'initiate_action',
            'type' => '3ds',
            'data' => [
                'request_id' => 'req_3ds_1',
                'card' => [
                    'id' => $card->wallester_card_id,
                    'last_four_digits' => '1234',
                ],
                'merchant' => [
                    'name' => 'Amazon',
                    'country_code' => 'USA',
                ],
                'transaction' => [
                    'amount' => '49.99',
                    'currency' => 'EUR',
                    'time' => '2026-07-01T10:00:00Z',
                ],
            ],
        ], [
            'Authorization' => 'Basic '.base64_encode('wallester-3ds:secret-3ds'),
            'X-Request-ID' => $xRequestId,
        ]);

        $response->assertOk()
            ->assertJson(['X-Request-ID' => $xRequestId])
            ->assertHeader('X-Request-ID', $xRequestId);

        $this->assertDatabaseHas('wallester_3ds_challenges', [
            'wallester_request_id' => 'req_3ds_1',
            'wallester_x_request_id' => $xRequestId,
            'customer_id' => $customer->id,
            'customer_wallester_card_id' => $card->id,
            'status' => Wallester3dsChallengeStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('customer_notifications', [
            'customer_id' => $customer->id,
            'type' => 'wallester.3ds_challenge',
        ]);
    }

    public function test_validate_returns_pending_until_customer_approves(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_acc_validate');
        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_validate_1',
            'type' => 'Virtual',
            'status' => 'Active',
        ]);

        $xRequestId = '22222222-2222-2222-2222-222222222222';

        $this->postJson('/api/webhooks/wallester/3ds/initiate-action-event', [
            'event' => 'initiate_action',
            'type' => '3ds',
            'data' => [
                'request_id' => 'req_validate_1',
                'card' => [
                    'id' => $card->wallester_card_id,
                    'last_four_digits' => '9999',
                ],
                'merchant' => ['name' => 'Shop'],
                'transaction' => ['time' => '2026-07-01T10:00:00Z'],
            ],
        ], $this->webhookHeaders($xRequestId))->assertOk();

        $this->postJson('/api/webhooks/wallester/3ds/validate-event', [
            'event' => 'validate',
            'type' => '3ds',
            'data' => ['request_id' => 'req_validate_1'],
        ], $this->webhookHeaders($xRequestId))
            ->assertOk()
            ->assertJson([
                'X-Request-ID' => $xRequestId,
                'status' => 'Pending',
            ]);

        $challenge = Wallester3dsChallenge::query()->where('wallester_request_id', 'req_validate_1')->firstOrFail();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/wallester/3ds-challenges/{$challenge->id}/approve")
            ->assertOk();

        $this->postJson('/api/webhooks/wallester/3ds/validate-event', [
            'event' => 'validate',
            'type' => '3ds',
            'data' => ['request_id' => 'req_validate_1'],
        ], $this->webhookHeaders($xRequestId))
            ->assertOk()
            ->assertJson([
                'status' => 'Success',
            ]);
    }

    public function test_webhook_requires_basic_auth(): void
    {
        $this->postJson('/api/webhooks/wallester/3ds/initiate-action-event', [], [
            'X-Request-ID' => '33333333-3333-3333-3333-333333333333',
        ])->assertUnauthorized();
    }

    /** @return array<string, string> */
    private function webhookHeaders(string $xRequestId): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode('wallester-3ds:secret-3ds'),
            'X-Request-ID' => $xRequestId,
        ];
    }
}

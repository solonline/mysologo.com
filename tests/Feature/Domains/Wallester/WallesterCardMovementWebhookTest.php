<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class WallesterCardMovementWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_webhook_ingests_card_purchase_movement(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_spend');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_spend_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event_type' => 'transaction',
            'id' => 'txn_card_1',
            'account_id' => 'wal_card_spend',
            'card_id' => 'card_spend_1',
            'account_amount' => 12.34,
            'account_currency_code' => 'EUR',
            'merchant_name' => 'Coffee Shop',
            'status' => 'cleared',
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'direction' => 'out',
            'amount' => 1234,
            'status' => FundingPartnerMovementStatus::Completed->value,
            'partner_external_id' => 'txn_card_1',
        ]);
    }

    public function test_webhook_card_movement_is_idempotent(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_idem');

        $payload = [
            'event_type' => 'transaction',
            'id' => 'txn_card_idem',
            'account_id' => 'wal_card_idem',
            'account_amount' => 5.00,
            'status' => 'cleared',
        ];

        $this->postJson('/api/webhooks/wallester', $payload)->assertOk();
        $this->postJson('/api/webhooks/wallester', $payload)->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 1);
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

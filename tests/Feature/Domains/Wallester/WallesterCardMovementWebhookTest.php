<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\CustomerBalance;
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

    public function test_webhook_ingests_nested_authorization_payload_from_production(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_nested_spend');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '26a1eaff-4652-48b1-b5f7-f3306050db45',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 10000,
            'pending' => 0,
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => '4dc0e494-c674-47bd-80ed-567e2aa19fdc',
                'card_id' => '26a1eaff-4652-48b1-b5f7-f3306050db45',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -0.9,
                'account_currency_code' => 'EUR',
                'group' => 'Purchase',
                'merchant_name' => 'DISTRICT',
                'response_code' => 'Approved',
                'is_declined' => false,
                'is_reversal' => false,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'direction' => 'out',
            'amount' => 90,
            'partner_external_id' => '4dc0e494-c674-47bd-80ed-567e2aa19fdc',
            'description' => 'DISTRICT',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 90,
            'available_before' => 10000,
            'available_after' => 9910,
            'pending_before' => 0,
            'pending_after' => 0,
            'balance_after' => 9910,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
            'description' => 'DISTRICT',
        ]);

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 9910,
        ]);
    }

    public function test_webhook_resolves_account_by_card_when_account_id_is_missing(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_only');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_only_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => 'txn_card_only',
                'card_id' => 'card_only_1',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -2.50,
                'account_currency_code' => 'EUR',
                'merchant_name' => 'Shop',
                'response_code' => 'Approved',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'amount' => 250,
            'partner_external_id' => 'txn_card_only',
        ]);
    }

    public function test_webhook_records_declined_authorizations_without_ledger(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_declined');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_declined',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 1000,
            'pending' => 0,
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => 'txn_declined',
                'card_id' => 'card_declined',
                'account_id' => 'wal_declined',
                'account_amount' => -5.00,
                'account_currency_code' => 'EUR',
                'merchant_name' => 'MesConvenienciaE',
                'response_code' => 'NotSufficientFunds',
                'is_declined' => true,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'amount' => 500,
            'status' => FundingPartnerMovementStatus::Failed->value,
            'partner_external_id' => 'txn_declined',
            'failure_reason' => 'NotSufficientFunds',
            'description' => 'MesConvenienciaE',
        ]);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 1000,
        ]);
    }

    public function test_webhook_card_movement_is_idempotent(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_idem');

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 5000,
            'pending' => 0,
        ]);

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
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 4500,
        ]);
    }

    public function test_webhook_settlement_does_not_double_debit_after_authorization(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_auth_settle');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_auth_settle',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 1000,
            'pending' => 0,
        ]);

        $authId = '4dc0e494-c674-47bd-80ed-567e2aa19fdc';
        $settlementId = 'ba9409c7-42ce-4ee1-bc74-7add3f1abe31';

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => $authId,
                'card_id' => 'card_auth_settle',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -0.9,
                'account_currency_code' => 'EUR',
                'group' => 'Purchase',
                'merchant_name' => 'DISTRICT',
                'response_code' => 'Approved',
                'is_declined' => false,
            ],
        ])->assertOk();

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'transaction',
            'data' => [
                'id' => $settlementId,
                'authorization_id' => $authId,
                'card_id' => 'card_auth_settle',
                'account_amount' => -0.9,
                'account_currency_code' => 'EUR',
                'group' => 'Purchase',
                'merchant_name' => 'DISTRICT',
                'authorization_code' => 'A9IZOT',
            ],
        ])->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'partner_external_id' => $authId,
            'amount' => 90,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 910,
        ]);
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

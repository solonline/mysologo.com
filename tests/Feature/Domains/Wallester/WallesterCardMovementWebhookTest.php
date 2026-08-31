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

    public function test_fx_fee_webhook_is_ignored_and_authorization_unifies_total(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, '50b270f8-31f8-463b-981f-67cc69591854');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
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

        $this->postJson('/api/webhooks/wallester', $this->lidlFxFeePayload())->assertOk();
        $this->assertDatabaseCount('funding_partner_movements', 0);
        $this->assertDatabaseCount('ledger_entries', 0);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 1);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'direction' => 'out',
            'amount' => 463,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'partner_external_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
            'description' => 'LidlGB',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 463,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'available' => 9537,
        ]);
    }

    public function test_fx_authorization_alone_unifies_fee_from_payload(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, '50b270f8-31f8-463b-981f-67cc69591854');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 1);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'amount' => 463,
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
        ]);
    }

    public function test_same_currency_purchase_does_not_store_fx_fee(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_same_ccy');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_same_ccy',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => 'txn_same_ccy',
                'card_id' => 'card_same_ccy',
                'account_id' => 'wal_same_ccy',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -12.34,
                'account_currency_code' => 'EUR',
                'transaction_amount' => -12.34,
                'transaction_currency_code' => 'EUR',
                'merchant_name' => 'Cafe',
                'response_code' => 'Approved',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'amount' => 1234,
            'currency' => 'EUR',
            'original_amount' => 1234,
            'original_currency' => 'EUR',
            'fees_amount' => null,
            'partner_external_id' => 'txn_same_ccy',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lidlFxFeePayload(): array
    {
        return [
            'event' => 'create',
            'type' => 'fee',
            'data' => [
                'id' => '2c4a1e9f-3145-4c00-bbf4-71adfd0ed934',
                'type' => 'AuthorizationForeignExchangePercentageFee',
                'status' => 'WaitingForClearing',
                'amount' => -0.09,
                'currency_code' => 'EUR',
                'company_id' => '1d299458-b005-4f5f-bd5c-ad42640ddcea',
                'account_id' => '50b270f8-31f8-463b-981f-67cc69591854',
                'card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
                'authorization_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
                'created_at' => '2026-08-26T16:21:07Z',
                'completed_at' => null,
                'updated_at' => null,
                'canceled_at' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lidlAuthorizationPayload(): array
    {
        return [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
                'card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
                'date' => '2026-08-26T17:21:00Z',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -4.54,
                'account_currency_code' => 'EUR',
                'acquiring_institution_id' => '425518',
                'acquiring_institution_country_code' => 'GBR',
                'group' => 'Purchase',
                'is_contactless' => true,
                'is_mass_transit' => false,
                'merchant_category_code' => '5411',
                'merchant_city' => 'BRISTOL',
                'merchant_country_code' => 'GBR',
                'merchant_id' => '000100800348809',
                'merchant_name' => 'LidlGB',
                'response_code' => 'Approved',
                'retrieval_reference_number' => '623817826362',
                'terminal_id' => '35366232',
                'transaction_amount' => -3.88,
                'transaction_currency_code' => 'GBP',
                'fees_amount' => 0.09,
                'is_reversal' => false,
                'is_declined' => false,
                'does_terminal_support_partial_approvals' => false,
                'approval_code' => '9M768E',
                'approved_amount' => -4.54,
                'created_at' => '2026-08-26T16:21:07Z',
            ],
        ];
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

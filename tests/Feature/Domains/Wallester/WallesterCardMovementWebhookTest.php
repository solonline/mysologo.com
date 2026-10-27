<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use App\Models\RailMirrorEvent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterCardMovementWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ACCOUNT_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const FEE_ACCOUNT_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    private const LIDL_ACCOUNT_ID = '50b270f8-31f8-463b-981f-67cc69591854';

    private const LIDL_AUTH_ID = '0aa86719-5a62-4ce7-abf5-577d2918db20';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

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
            'wallester_allocated' => 10000,
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
            'wallester_allocated' => 9910,
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
            'wallester_allocated' => 1000,
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
                'transaction_amount' => -4.29,
                'transaction_currency_code' => 'GBP',
                'fees_amount' => 0.10,
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
        $this->assertDatabaseCount('funding_partner_movements', 1);
        $this->assertDatabaseMissing('funding_partner_movements', [
            'type' => FundingPartnerMovementType::Fee->value,
        ]);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 1000,
        ]);
    }

    public function test_webhook_card_movement_is_idempotent(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_card_idem');

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5000,
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
            'wallester_allocated' => 4500,
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
            'wallester_allocated' => 1000,
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
            'wallester_allocated' => 910,
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
            'wallester_allocated' => 10000,
            'pending' => 0,
        ]);

        $this->postJson('/api/webhooks/wallester', $this->lidlFxFeePayload())->assertOk();
        $this->assertDatabaseCount('funding_partner_movements', 0);
        $this->assertDatabaseCount('ledger_entries', 0);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 2);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'direction' => 'out',
            'amount' => 454,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'platform_fees_amount' => null,
            'partner_external_id' => '0aa86719-5a62-4ce7-abf5-577d2918db20',
            'description' => 'LidlGB',
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'type' => FundingPartnerMovementType::Fee->value,
            'amount' => 9,
            'fees_amount' => 9,
            'platform_fees_amount' => null,
            'description' => 'Câmbio',
            'idempotency_key' => 'fx-fees:0aa86719-5a62-4ce7-abf5-577d2918db20',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 454,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 9,
            'entry_type' => LedgerEntryType::PLATFORM_FEE->value,
            'description' => 'Câmbio',
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 9537,
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

        $this->assertDatabaseCount('funding_partner_movements', 2);
        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'amount' => 454,
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'platform_fees_amount' => null,
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
            'platform_fees_amount' => null,
            'partner_external_id' => 'txn_same_ccy',
        ]);
    }

    public function test_fx_purchase_collects_platform_fee_when_percentage_configured(): void
    {
        $this->seedWallesterSettings();
        $this->setFxPercentage('0.01');

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::LIDL_ACCOUNT_ID);
        $platform = $this->createPlatformMaster();

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 10000,
            'wise_allocated' => 10000,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::LIDL_ACCOUNT_ID, (string) $customer->id)
            ->andReturn(['available_amount' => 100.00, 'balance' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ACCOUNT_ID, 'platform')
            ->andReturn(['available_amount' => 1000.00, 'balance' => 1000.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::LIDL_ACCOUNT_ID
                    && $payload['to_account_id'] === self::MASTER_ACCOUNT_ID
                    && $payload['amount'] === 0.05
                    && $payload['description'] === 'Câmbio Yoop';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_fx_platform_1']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $purchase = FundingPartnerMovement::query()
            ->where('partner_external_id', self::LIDL_AUTH_ID)
            ->where('type', FundingPartnerMovementType::CardPurchase)
            ->firstOrFail();

        $this->assertSame(5, (int) $purchase->platform_fees_amount);
        $this->assertSame(454, (int) $purchase->amount);

        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => FundingPartnerMovementType::Fee->value,
            'amount' => 14,
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'idempotency_key' => 'fx-fees:'.self::LIDL_AUTH_ID,
            'description' => 'Câmbio',
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:'.self::LIDL_AUTH_ID.':out',
            'status' => FundingPartnerMovementStatus::Completed->value,
            'amount' => 5,
            'currency' => 'EUR',
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:'.self::LIDL_AUTH_ID.':in',
            'status' => FundingPartnerMovementStatus::Completed->value,
            'amount' => 5,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 454,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit->value,
            'amount' => 14,
            'entry_type' => LedgerEntryType::PLATFORM_FEE->value,
            'description' => 'Câmbio',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Credit->value,
            'amount' => 5,
            'entry_type' => LedgerEntryType::PLATFORM_FEE_INCOME->value,
            'description' => 'Câmbio Yoop',
        ]);
        $this->assertDatabaseMissing('ledger_entries', [
            'customer_id' => $platform->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 9532,
            'wise_allocated' => 9532,
        ]);
        $this->assertDatabaseHas('rail_mirror_events', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => 'debit',
            'amount' => 14,
            'reason' => 'fx_fee',
        ]);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 9532,
            'wise_allocated' => 9532,
        ]);
        $this->assertSame(1, RailMirrorEvent::query()->where('reason', 'fx_fee')->count());
    }

    public function test_fx_purchase_sends_platform_fee_to_sub_master_account(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ACCOUNT_ID,
        ]);
        $this->setFxPercentage('0.01');

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::LIDL_ACCOUNT_ID);
        $platform = $this->createPlatformMaster();
        $feeAccount = $this->createWallesterAccount(
            $platform,
            IntegrationAccountStatus::ACTIVE,
            self::FEE_ACCOUNT_ID,
            [
                'is_main' => false,
                'receiver_name' => 'Sub Conta Master',
                'product_id' => 'prod_fee_'.$platform->id,
                'company_id' => 'comp_fee_'.$platform->id,
            ],
        );

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 10000,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::LIDL_ACCOUNT_ID, (string) $customer->id)
            ->andReturn(['available_amount' => 100.00, 'balance' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->with(self::FEE_ACCOUNT_ID, 'platform')
            ->andReturn(['available_amount' => 1.00, 'balance' => 1.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::LIDL_ACCOUNT_ID
                    && $payload['to_account_id'] === self::FEE_ACCOUNT_ID
                    && $payload['amount'] === 0.05
                    && $payload['description'] === 'Câmbio Yoop';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_fx_platform_fee_sub']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:'.self::LIDL_AUTH_ID.':in',
            'funding_account_id' => $feeAccount->id,
            'amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Credit->value,
            'amount' => 5,
            'entry_type' => LedgerEntryType::PLATFORM_FEE_INCOME->value,
            'description' => 'Câmbio Yoop',
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5,
        ]);
    }

    public function test_same_currency_purchase_does_not_collect_platform_fx_fee_when_percentage_set(): void
    {
        $this->setFxPercentage('0.01');
        Http::preventStrayRequests();

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_same_ccy_pct');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_same_ccy_pct',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => 'txn_same_ccy_pct',
                'card_id' => 'card_same_ccy_pct',
                'account_id' => 'wal_same_ccy_pct',
                'type' => 'payWaveLowValuePurchase',
                'account_amount' => -12.34,
                'account_currency_code' => 'EUR',
                'transaction_amount' => -12.34,
                'transaction_currency_code' => 'EUR',
                'merchant_name' => 'Cafe',
                'response_code' => 'Approved',
            ],
        ])->assertOk();

        $this->assertDatabaseCount('funding_partner_movements', 1);
        $this->assertDatabaseHas('funding_partner_movements', [
            'partner_external_id' => 'txn_same_ccy_pct',
            'platform_fees_amount' => null,
        ]);
    }

    public function test_platform_fx_fee_failure_does_not_fail_webhook(): void
    {
        $this->setFxPercentage('0.01');
        Http::preventStrayRequests();

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::LIDL_ACCOUNT_ID);

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        $this->postJson('/api/webhooks/wallester', $this->lidlAuthorizationPayload())->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'partner_external_id' => self::LIDL_AUTH_ID,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'amount' => 454,
            'platform_fees_amount' => null,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => FundingPartnerMovementType::Fee->value,
            'amount' => 9,
            'platform_fees_amount' => null,
        ]);
        $this->assertDatabaseCount('funding_partner_movements', 2);
    }

    public function test_accepted_reversal_credits_customer_and_reverses_proportional_fx(): void
    {
        $this->seedWallesterSettings();
        $this->setFxPercentage('0.01');

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::LIDL_ACCOUNT_ID);
        $platform = $this->createPlatformMaster();

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 20000,
            'wise_allocated' => 20000,
            'pending' => 0,
        ]);

        $authId = '42682b3e-084d-4674-81ea-8d7450bd0495';
        $reversalId = 'aa9481d9-92e1-4ad9-b1ed-3656d3ee9ba3';

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::LIDL_ACCOUNT_ID, (string) $customer->id)
            ->andReturn(['available_amount' => 200.00, 'balance' => 200.00]);
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ACCOUNT_ID, 'platform')
            ->andReturn(['available_amount' => 1000.00, 'balance' => 1000.00]);
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ACCOUNT_ID, (string) $platform->id)
            ->andReturn(['available_amount' => 1000.00, 'balance' => 1000.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::LIDL_ACCOUNT_ID
                    && $payload['to_account_id'] === self::MASTER_ACCOUNT_ID
                    && $payload['description'] === 'Câmbio Yoop';
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_fx_out']);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::MASTER_ACCOUNT_ID
                    && $payload['to_account_id'] === self::LIDL_ACCOUNT_ID
                    && $payload['description'] === 'Câmbio Yoop (reversão)';
            }), 'platform')
            ->andReturn(['id' => 'pay_fx_rev']);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->postJson('/api/webhooks/wallester', $this->asdaPetrolAuthorizationPayload($authId))->assertOk();
        $this->postJson('/api/webhooks/wallester', $this->asdaPetrolReversalPayload($authId, $reversalId))->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'partner_external_id' => $authId,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'amount' => 11536,
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'partner_external_id' => $reversalId,
            'type' => FundingPartnerMovementType::CardRefund->value,
            'amount' => 4682,
            'status' => FundingPartnerMovementStatus::Completed->value,
            'failure_reason' => null,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'fx-platform-reversal:'.$reversalId.':out',
            'status' => FundingPartnerMovementStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::CARD_UNLOAD->value,
            'amount' => 4682,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PAYMENT_REFUND->value,
            'description' => 'Câmbio',
            'amount' => 141,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 12941,
            'wise_allocated' => 12941,
        ]);
        $this->assertDatabaseHas('rail_mirror_events', [
            'customer_id' => $customer->id,
            'direction' => 'credit',
            'amount' => 141,
            'reason' => 'fx_fee_reversal',
        ]);
    }

    public function test_repair_command_completes_failed_accepted_reversal(): void
    {
        $this->seedWallesterSettings();

        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::LIDL_ACCOUNT_ID);
        $this->createPlatformMaster();

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 6854,
            'wise_allocated' => 6854,
            'pending' => 0,
        ]);

        $purchase = FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => 'out',
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 11536,
            'currency' => 'EUR',
            'original_amount' => 9900,
            'original_currency' => 'GBP',
            'fees_amount' => 231,
            'partner_external_id' => '42682b3e-084d-4674-81ea-8d7450bd0495',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => 'webhook',
            'idempotency_key' => 'card:42682b3e-084d-4674-81ea-8d7450bd0495:out',
            'description' => 'ASDAPETROL/UPT',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => 'out',
            'type' => FundingPartnerMovementType::Fee,
            'amount' => 231,
            'currency' => 'EUR',
            'fees_amount' => 231,
            'partner_external_id' => 'fx-fees:42682b3e-084d-4674-81ea-8d7450bd0495',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => 'webhook',
            'idempotency_key' => 'fx-fees:42682b3e-084d-4674-81ea-8d7450bd0495',
            'description' => 'Câmbio',
            'reference_type' => $purchase->getMorphClass(),
            'reference_id' => $purchase->id,
        ]);

        $refund = FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => 'in',
            'type' => FundingPartnerMovementType::CardRefund,
            'amount' => 4682,
            'currency' => 'EUR',
            'original_amount' => 4018,
            'original_currency' => 'GBP',
            'partner_external_id' => 'aa9481d9-92e1-4ad9-b1ed-3656d3ee9ba3',
            'status' => FundingPartnerMovementStatus::Failed,
            'failure_reason' => 'AcceptedReversal',
            'source' => 'webhook',
            'idempotency_key' => 'card:aa9481d9-92e1-4ad9-b1ed-3656d3ee9ba3:in',
            'description' => 'ASDAPETROL/UPT',
            'metadata' => [
                'original_authorization_id' => '42682b3e-084d-4674-81ea-8d7450bd0495',
                'is_reversal' => true,
                'response_code' => 'AcceptedReversal',
                'is_declined' => false,
            ],
        ]);

        $this->artisan('wallester:repair-accepted-reversals', ['--movement' => $refund->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('funding_partner_movements', [
            'id' => $refund->id,
            'status' => FundingPartnerMovementStatus::Completed->value,
            'failure_reason' => null,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::CARD_UNLOAD->value,
            'amount' => 4682,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PAYMENT_REFUND->value,
            'description' => 'Câmbio',
            'amount' => 94,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 11630,
            'wise_allocated' => 11630,
        ]);
    }

    private function setFxPercentage(string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'fx.percentage'],
            [
                'label' => 'Percentagem da plataforma',
                'type' => 'text',
                'value' => $value,
            ],
        );
    }

    private function createPlatformMaster(): Customer
    {
        $platform = Customer::factory()->create([
            'type' => 'platform',
            'email' => 'master@yoop.test',
            'name' => 'Platform',
        ]);

        $this->createWallesterAccount(
            $platform,
            IntegrationAccountStatus::ACTIVE,
            self::MASTER_ACCOUNT_ID,
            ['is_main' => true, 'funding_currency' => 'EUR'],
        );

        return $platform;
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

    /**
     * @return array<string, mixed>
     */
    private function asdaPetrolAuthorizationPayload(string $authId): array
    {
        return [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => $authId,
                'card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
                'account_id' => self::LIDL_ACCOUNT_ID,
                'date' => '2026-09-08T09:21:00Z',
                'type' => 'AutomatedFuelDispenser',
                'account_amount' => -115.36,
                'account_currency_code' => 'EUR',
                'group' => 'Purchase',
                'merchant_category_code' => '5542',
                'merchant_name' => 'ASDAPETROL/UPT',
                'response_code' => 'Approved',
                'transaction_amount' => -99.00,
                'transaction_currency_code' => 'GBP',
                'fees_amount' => 2.31,
                'is_reversal' => false,
                'is_declined' => false,
                'approved_amount' => -115.36,
                'created_at' => '2026-09-08T09:21:00Z',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function asdaPetrolReversalPayload(string $authId, string $reversalId): array
    {
        return [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => $reversalId,
                'card_id' => '3b77a4ec-338e-40e2-a7e9-efd8cb5cf6a7',
                'account_id' => self::LIDL_ACCOUNT_ID,
                'date' => '2026-09-08T09:24:00Z',
                'type' => 'AutomatedFuelDispenser',
                'account_amount' => 46.82,
                'account_currency_code' => 'EUR',
                'group' => 'Reversal',
                'merchant_category_code' => '5542',
                'merchant_name' => 'ASDAPETROL/UPT',
                'response_code' => 'AcceptedReversal',
                'transaction_amount' => 40.18,
                'transaction_currency_code' => 'GBP',
                'original_authorization_id' => $authId,
                'authorization_id' => $authId,
                'is_reversal' => true,
                'is_declined' => false,
                'created_at' => '2026-09-08T09:24:00Z',
            ],
        ];
    }

    public function test_webhook_stores_atm_withdrawal_and_fee_apart_from_fx(): void
    {
        $customer = $this->createActiveCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'ade77db0-ea4a-4519-89a2-2400da18b8ae');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => '048e25fd-2419-4790-8695-1f1b74961a58',
            'type' => 'Physical',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 20000,
            'wise_allocated' => 20000,
            'pending' => 0,
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => 'a7a7677e-f8da-4092-8ba2-d333543dcd67',
                'card_id' => '048e25fd-2419-4790-8695-1f1b74961a58',
                'type' => 'ATMWithdrawal',
                'account_amount' => -40,
                'account_currency_code' => 'EUR',
                'group' => 'Withdraw',
                'merchant_name' => 'PapelariaTacticasSere',
                'response_code' => 'WithdrawalsSwitchedOff',
                'is_declined' => true,
                'is_reversal' => false,
            ],
        ])->assertOk();

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => '6ed857b6-ca57-4817-a179-82cd9bc9c2cc',
                'card_id' => '048e25fd-2419-4790-8695-1f1b74961a58',
                'type' => 'ATMWithdrawal',
                'account_amount' => -40,
                'account_currency_code' => 'EUR',
                'transaction_amount' => -40,
                'transaction_currency_code' => 'EUR',
                'fees_amount' => 2,
                'group' => 'Withdraw',
                'merchant_name' => 'PapelariaTacticasSere',
                'response_code' => 'Approved',
                'is_declined' => false,
                'approved_amount' => -40,
            ],
        ])->assertOk();

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'create',
            'type' => 'fee',
            'data' => [
                'id' => '34d6d393-245e-4e14-9437-835b6e656744',
                'type' => 'AuthorizationATMWithdrawalEEAComplexFee',
                'amount' => -2,
                'currency_code' => 'EUR',
                'account_id' => 'ade77db0-ea4a-4519-89a2-2400da18b8ae',
                'card_id' => '048e25fd-2419-4790-8695-1f1b74961a58',
                'authorization_id' => '6ed857b6-ca57-4817-a179-82cd9bc9c2cc',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('funding_partner_movements', [
            'partner_external_id' => 'a7a7677e-f8da-4092-8ba2-d333543dcd67',
            'type' => FundingPartnerMovementType::CardWithdrawal->value,
            'status' => FundingPartnerMovementStatus::Failed->value,
            'failure_reason' => 'WithdrawalsSwitchedOff',
            'description' => 'PapelariaTacticasSere',
            'amount' => 4000,
        ]);

        $withdrawal = FundingPartnerMovement::query()
            ->where('partner_external_id', '6ed857b6-ca57-4817-a179-82cd9bc9c2cc')
            ->first();

        $this->assertNotNull($withdrawal);
        $this->assertSame(FundingPartnerMovementType::CardWithdrawal, $withdrawal->type);
        $this->assertSame('Saque', $withdrawal->displayTypeLabel());
        $this->assertSame(4000, (int) $withdrawal->amount);
        $this->assertSame(200, (int) $withdrawal->fees_amount);

        $fee = FundingPartnerMovement::query()
            ->where('reference_id', $withdrawal->id)
            ->where('type', FundingPartnerMovementType::CardWithdrawalFee)
            ->first();

        $this->assertNotNull($fee);
        $this->assertSame('Taxa de saque', $fee->displayTypeLabel());
        $this->assertSame('Taxa de saque', $fee->description);
        $this->assertSame(200, (int) $fee->amount);
        $this->assertSame('34d6d393-245e-4e14-9437-835b6e656744', $fee->metadata['atm_withdrawal_fee_id'] ?? null);

        $this->assertDatabaseMissing('funding_partner_movements', [
            'description' => 'Câmbio',
        ]);
        $this->assertDatabaseMissing('funding_partner_movements', [
            'idempotency_key' => 'fx-platform:6ed857b6-ca57-4817-a179-82cd9bc9c2cc:out',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'reference_id' => $withdrawal->id,
            'entry_type' => LedgerEntryType::CARD_WITHDRAWAL->value,
            'amount' => 4000,
            'description' => 'PapelariaTacticasSere',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_id' => $fee->id,
            'entry_type' => LedgerEntryType::CARD_WITHDRAWAL_FEE->value,
            'amount' => 200,
            'description' => 'Taxa de saque',
        ]);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'wallester_allocated' => 15800,
            'wise_allocated' => 15800,
        ]);

        $this->postJson('/api/webhooks/wallester', [
            'event' => 'notification',
            'type' => 'authorization',
            'data' => [
                'id' => '6ed857b6-ca57-4817-a179-82cd9bc9c2cc',
                'card_id' => '048e25fd-2419-4790-8695-1f1b74961a58',
                'type' => 'ATMWithdrawal',
                'account_amount' => -40,
                'account_currency_code' => 'EUR',
                'fees_amount' => 2,
                'group' => 'Withdraw',
                'merchant_name' => 'PapelariaTacticasSere',
                'is_declined' => false,
            ],
        ])->assertOk();

        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(15800, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wallester_allocated'));
        $this->assertSame(15800, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('wise_allocated'));
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

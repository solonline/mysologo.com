<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\SyncWallesterCardMovementsService;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\CustomerBalance;
use App\Models\CustomerWallesterCard;
use App\Models\FundingPartnerMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class SyncWallesterCardMovementsCommandTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWallesterSettings();
    }

    public function test_command_imports_missing_card_transactions(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sync_card');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sync_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5000,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardTransactions')
            ->once()
            ->andReturn([
                'total_records_number' => 1,
                'transactions' => [[
                    'id' => 'txn_sync_1',
                    'account_id' => 'wal_sync_card',
                    'card_id' => 'card_sync_1',
                    'type' => 'purchase',
                    'status' => 'cleared',
                    'account_amount' => 15.50,
                    'account_currency_code' => 'EUR',
                    'merchant_name' => 'Cafe Sync',
                ]],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);
        $this->app->forgetInstance(SyncWallesterCardMovementsService::class);

        $this->artisan('wallester:sync-card-movements')->assertSuccessful();

        $this->assertDatabaseHas('funding_partner_movements', [
            'funding_account_id' => $account->id,
            'customer_wallester_card_id' => $card->id,
            'type' => FundingPartnerMovementType::CardPurchase->value,
            'direction' => FundingPartnerMovementDirection::Out->value,
            'amount' => 1550,
            'status' => FundingPartnerMovementStatus::Completed->value,
            'source' => FundingPartnerMovementSource::Sync->value,
            'partner_external_id' => 'txn_sync_1',
            'description' => 'Cafe Sync',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::CARD_PURCHASE->value,
            'amount' => 1550,
        ]);
    }

    public function test_command_skips_already_recorded_transactions(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sync_dup');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sync_dup',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Main Card',
        ]);

        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account->getMorphClass(),
            'funding_account_id' => $account->id,
            'external_account_id' => $account->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 900,
            'currency' => 'EUR',
            'customer_wallester_card_id' => $card->id,
            'partner_external_id' => 'txn_sync_dup',
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:txn_sync_dup:out',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardTransactions')
            ->once()
            ->andReturn([
                'total_records_number' => 1,
                'transactions' => [[
                    'id' => 'txn_sync_dup',
                    'account_id' => 'wal_sync_dup',
                    'card_id' => 'card_sync_dup',
                    'type' => 'purchase',
                    'status' => 'cleared',
                    'account_amount' => 9.00,
                    'account_currency_code' => 'EUR',
                ]],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);
        $this->app->forgetInstance(SyncWallesterCardMovementsService::class);

        $this->artisan('wallester:sync-card-movements')
            ->assertSuccessful();

        $this->assertDatabaseCount('funding_partner_movements', 1);
    }

    public function test_command_ignores_non_operational_cards(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sync_closed');

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sync_closed',
            'type' => 'Virtual',
            'status' => 'Closed',
            'name' => 'Closed Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldNotReceive('listCardTransactions');

        $this->app->instance(WallesterGatewayInterface::class, $gateway);
        $this->app->forgetInstance(SyncWallesterCardMovementsService::class);

        $this->artisan('wallester:sync-card-movements')
            ->assertSuccessful()
            ->expectsOutputToContain('0 cartão(ões)');
    }

    public function test_command_can_filter_by_specific_card(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sync_filter');

        $targetCard = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sync_target',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Target Card',
        ]);

        CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sync_other',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Other Card',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('listCardTransactions')
            ->once()
            ->with('card_sync_target', Mockery::type('array'), (string) $customer->id)
            ->andReturn([
                'total_records_number' => 0,
                'transactions' => [],
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallester:sync-card-movements', ['--card' => $targetCard->id])
            ->assertSuccessful()
            ->expectsOutputToContain('1 cartão(ões)');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}

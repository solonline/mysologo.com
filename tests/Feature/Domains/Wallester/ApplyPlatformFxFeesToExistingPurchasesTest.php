<?php

namespace Tests\Feature\Domains\Wallester;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\FundingPartnerMovement;
use App\Models\LedgerEntry;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class ApplyPlatformFxFeesToExistingPurchasesTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ACCOUNT_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const ACCOUNT_ID = 'wal_legacy_fx';

    private const AUTH_ID = 'legacy-fx-auth';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_splits_legacy_amount_and_applies_platform_fee(): void
    {
        $this->seedWallesterSettings();
        Setting::query()->updateOrCreate(
            ['key' => 'fx.percentage'],
            ['label' => 'Percentagem da plataforma', 'type' => 'text', 'value' => '0.01'],
        );

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, self::ACCOUNT_ID);
        $platform = $this->createPlatformMaster();

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 9537,
            'pending' => 0,
        ]);

        $purchase = FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => self::ACCOUNT_ID,
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 463,
            'currency' => 'EUR',
            'original_amount' => 388,
            'original_currency' => 'GBP',
            'fees_amount' => 9,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:'.self::AUTH_ID.':out',
            'partner_external_id' => self::AUTH_ID,
            'description' => 'LidlGB',
            'metadata' => [
                'account_amount' => -4.54,
                'account_currency_code' => 'EUR',
                'transaction_amount' => -3.88,
                'transaction_currency_code' => 'GBP',
                'fees_amount' => 0.09,
            ],
        ]);

        LedgerEntry::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit,
            'amount' => 463,
            'available_before' => 10000,
            'available_after' => 9537,
            'pending_before' => 0,
            'pending_after' => 0,
            'balance_after' => 9537,
            'entry_type' => LedgerEntryType::CARD_PURCHASE,
            'reference_type' => $purchase->getMorphClass(),
            'reference_id' => $purchase->id,
            'description' => 'LidlGB',
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::ACCOUNT_ID, (string) $customer->id)
            ->andReturn(['available_amount' => 100.00, 'balance' => 100.00]);
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ACCOUNT_ID, 'platform')
            ->andReturn(['available_amount' => 1000.00, 'balance' => 1000.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['from_account_id'] === self::ACCOUNT_ID
                    && $payload['to_account_id'] === self::MASTER_ACCOUNT_ID
                    && $payload['amount'] === 0.05;
            }), (string) $customer->id)
            ->andReturn(['id' => 'pay_fx_backfill_1']);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallester:apply-platform-fx-fees')
            ->assertSuccessful();

        $purchase->refresh();
        $this->assertSame(454, (int) $purchase->amount);
        $this->assertSame(5, (int) $purchase->platform_fees_amount);

        $this->assertDatabaseHas('funding_partner_movements', [
            'type' => FundingPartnerMovementType::Fee->value,
            'amount' => 14,
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'idempotency_key' => 'fx-fees:'.self::AUTH_ID,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PLATFORM_FEE->value,
            'amount' => 5,
            'description' => 'Câmbio',
        ]);
        $this->assertDatabaseMissing('ledger_entries', [
            'customer_id' => $platform->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
        ]);
        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 9532,
        ]);

        $this->artisan('wallester:apply-platform-fx-fees')
            ->assertSuccessful();

        $this->assertSame(5, (int) $purchase->fresh()->platform_fees_amount);
        $this->assertSame(1, LedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_type', LedgerEntryType::PLATFORM_FEE)
            ->count());
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
}

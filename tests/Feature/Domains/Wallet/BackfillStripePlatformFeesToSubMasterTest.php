<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\BackfillStripePlatformFeesToSubMasterService;
use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class BackfillStripePlatformFeesToSubMasterTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const FEE_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_creates_pending_rows_and_holds_funded_fee_when_master_lacks_liquidity(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'master-fee@yoop.test']);
        $retail = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'name' => 'Bruno Trindade',
        ]);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 125,
            'balance' => 125,
        ]);
        $fee = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);
        $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_retail_fee');

        $funded = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 400,
            'stripe_fee' => 65,
            'platform_fee' => 201,
            'net_amount' => 134,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funded_at' => now(),
        ]);
        $stripeOnly = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 6,
            'net_amount' => 994,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ID, (string) $platform->id)
            ->andReturn(['available_amount' => 1.25, 'balance' => 1.25]);
        $gateway->shouldNotReceive('accountTransfer');
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(BackfillStripePlatformFeesToSubMasterService::class)->execute();

        $this->assertSame(0, $result['landed_cents']);
        $this->assertSame(201, $result['pending_liquidity_cents']);
        $this->assertSame(6, $result['pending_stripe_cents']);
        $this->assertSame(0, $result['fx_already_cents']);
        $this->assertSame(76, $result['liquidity_shortfall_cents']);

        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'stripe-platform-fee:'.$funded->id.':in',
            'funding_account_id' => $fee->id,
            'amount' => 201,
            'status' => FundingPartnerMovementStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('funding_partner_movements', [
            'idempotency_key' => 'stripe-platform-fee:'.$stripeOnly->id.':in',
            'funding_account_id' => $fee->id,
            'amount' => 6,
            'status' => FundingPartnerMovementStatus::Pending->value,
        ]);
        $this->assertDatabaseMissing('funding_partner_movements', [
            'idempotency_key' => 'stripe-platform-fee:'.$funded->id.':out',
        ]);
    }

    public function test_deletes_pending_rows_for_transactions_before_accounting_cutoff(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'cutoff-fee@yoop.test']);
        $retail = $this->createRetailCustomer(['status' => CustomerLifecycleStatus::ACTIVE]);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 125,
        ]);
        $fee = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);

        $historical = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_100,
            'stripe_fee' => 61,
            'platform_fee' => 6,
            'net_amount' => 1_033,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::NotRequired,
            'stripe_created_at' => '2026-07-22 10:46:20',
        ]);

        \App\Models\FundingPartnerMovement::query()->create([
            'provider' => 'wallester',
            'funding_account_type' => $fee::class,
            'funding_account_id' => $fee->id,
            'external_account_id' => $fee->wallester_account_id,
            'direction' => 'in',
            'type' => 'account_transfer',
            'amount' => 6,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Pending,
            'source' => 'api',
            'idempotency_key' => 'stripe-platform-fee:'.$historical->id.':in',
            'description' => 'Taxa Yoop',
            'reference_type' => $historical->getMorphClass(),
            'reference_id' => $historical->id,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with(self::MASTER_ID, (string) $platform->id)
            ->andReturn(['available_amount' => 1.25, 'balance' => 1.25]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(BackfillStripePlatformFeesToSubMasterService::class)->execute();

        $this->assertSame(0, $result['pending_stripe_cents']);
        $this->assertSame(0, $result['pending_liquidity_cents']);
        $this->assertDatabaseMissing('funding_partner_movements', [
            'idempotency_key' => 'stripe-platform-fee:'.$historical->id.':in',
        ]);
    }

    public function test_command_is_scheduled_every_six_hours_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'wallet:backfill-stripe-fees-to-sub-master'));

        $this->assertNotNull($event);
        $this->assertSame('0 */6 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}

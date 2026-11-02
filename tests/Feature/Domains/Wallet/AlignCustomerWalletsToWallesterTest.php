<?php

namespace Tests\Feature\Domains\Wallet;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\AlignCustomerWalletsToWallesterService;
use App\Domains\Wise\Enums\WiseTransferStatus;
use App\Models\CustomerBalance;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use App\Models\WiseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class AlignCustomerWalletsToWallesterTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_sets_ledger_and_wise_to_wallester_available(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_customer', [
            'available_amount' => 1000,
            'balance' => 1500,
            'blocked_amount' => 500,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_customer', 'platform')
            ->andReturn([
                'available_amount' => 7.50,
                'balance' => 12.50,
                'blocked_amount' => 5.00,
            ]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester')->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(750, (int) $balance->wallester_allocated);
        $this->assertSame(750, (int) $balance->wise_allocated);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
            'amount' => 250,
            'description' => AlignCustomerWalletsToWallesterService::DESCRIPTION,
        ]);
    }

    public function test_dry_run_does_not_change_balances(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer_dry']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_dry', [
            'available_amount' => 1000,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_dry', 'platform')
            ->andReturn(['available_amount' => 7.50, 'balance' => 7.50]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester', ['--dry-run' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(500, (int) $balance->wallester_allocated);
        $this->assertSame(200, (int) $balance->wise_allocated);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_failed_wallester_sync_does_not_change_balances(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'align_customer_fail']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_align_fail', [
            'available_amount' => 1000,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'wise_allocated' => 200,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_align_fail', 'platform')
            ->andThrow(new RuntimeException('wallester down'));
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester')->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(500, (int) $balance->wallester_allocated);
        $this->assertSame(200, (int) $balance->wise_allocated);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_mirror_moves_orphan_pending_and_captive_into_wallester_available_once(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'mirror_carlos']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_mirror_carlos', [
            'available_amount' => 146,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 146,
            'wise_allocated' => 578,
            'pending' => 432,
        ]);

        $open = $this->createWiseTransfer($customer->id, [
            'amount_cents' => 50,
            'status' => WiseTransferStatus::IncomingPaymentWaiting,
        ]);
        $settled = $this->createWiseTransfer($customer->id, [
            'amount_cents' => 35,
            'status' => WiseTransferStatus::OutgoingPaymentSent,
            'settled_at' => now(),
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_mirror_carlos', 'platform')
            ->andReturn(['available_amount' => 6.28, 'balance' => 6.28]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester', ['--mirror' => true, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(432, (int) CustomerBalance::query()->where('customer_id', $customer->id)->value('pending'));
        $this->assertNull($open->fresh()->refunded_at);

        $this->artisan('wallet:align-customers-to-wallester', ['--mirror' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(628, (int) $balance->wallester_allocated);
        $this->assertSame(628, (int) $balance->wise_allocated);
        $this->assertSame(0, (int) $balance->pending);

        $open->refresh();
        $settled->refresh();
        $this->assertSame(WiseTransferStatus::Cancelled, $open->status);
        $this->assertNotNull($open->refunded_at);
        $this->assertNull($open->settled_at);
        $this->assertSame(WiseTransferStatus::OutgoingPaymentSent, $settled->status);
        $this->assertNull($settled->refunded_at);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'amount' => 432,
            'description' => AlignCustomerWalletsToWallesterService::PENDING_DESCRIPTION,
            'available_before' => 146,
            'available_after' => 146,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
            'amount' => 482,
            'description' => AlignCustomerWalletsToWallesterService::DESCRIPTION,
            'available_before' => 146,
            'available_after' => 628,
        ]);
        $this->assertSame(2, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }

    public function test_mirror_keeps_pending_covered_by_stripe_payout(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'mirror_bruno']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_mirror_bruno', [
            'available_amount' => 61,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 61,
            'wise_allocated' => 61,
            'pending' => 767,
        ]);

        $payout = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'direction' => TransactionDirection::Credit,
            'payment_method_type' => 'card',
            'amount' => 800,
            'stripe_fee' => 33,
            'platform_fee' => 0,
            'net_amount' => 767,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_mirror_bruno', 'platform')
            ->andReturn(['available_amount' => 0.61, 'balance' => 0.61]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester', ['--mirror' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(61, (int) $balance->wallester_allocated);
        $this->assertSame(61, (int) $balance->wise_allocated);
        $this->assertSame(767, (int) $balance->pending);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
        $this->assertSame('succeeded', $payout->fresh()->status);
        $this->assertSame(PartnerFundingStatus::AwaitingStripePayout, $payout->fresh()->partner_funding_status);
    }

    public function test_mirror_does_nothing_when_books_already_match_wallester(): void
    {
        $customer = $this->createRetailCustomer(['username' => 'mirror_even']);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_mirror_even', [
            'available_amount' => 750,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 750,
            'wise_allocated' => 750,
            'pending' => 0,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->with('wal_mirror_even', 'platform')
            ->andReturn(['available_amount' => 7.50, 'balance' => 7.50]);
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->artisan('wallet:align-customers-to-wallester', ['--mirror' => true])
            ->assertSuccessful();

        $balance = CustomerBalance::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(750, (int) $balance->wallester_allocated);
        $this->assertSame(750, (int) $balance->wise_allocated);
        $this->assertSame(0, (int) $balance->pending);
        $this->assertSame(0, LedgerEntry::query()->where('customer_id', $customer->id)->count());
    }

    private function createWiseTransfer(int $customerId, array $overrides): WiseTransfer
    {
        return WiseTransfer::query()->create(array_merge([
            'customer_id' => $customerId,
            'wise_transfer_id' => random_int(100000, 999999999),
            'wise_batch_group_id' => (string) Str::uuid(),
            'wise_recipient_id' => random_int(1000, 999999),
            'beneficiary_name' => 'Beneficiário',
            'reference' => 'ref-'.Str::random(8),
            'currency' => 'EUR',
            'amount_cents' => 50,
            'idempotency_key' => (string) Str::uuid(),
            'status' => WiseTransferStatus::IncomingPaymentWaiting,
        ], $overrides));
    }
}

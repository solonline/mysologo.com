<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\PlatformRevenueService;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class PlatformRevenueDashboardTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();
    }

    public function test_platform_revenue_page_shows_fee_totals_and_transactions(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $platform = $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer([
            'name' => 'Cliente Taxa Platform',
        ]);

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Top-up com taxa',
            'stripe_created_at' => now(),
        ]);

        LedgerEntry::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'direction' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT,
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'description' => PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$transaction->id,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.platform-revenue'))
            ->assertOk()
            ->assertSee('Receita Platform')
            ->assertSee('Liquidado')
            ->assertSee('Aguarda liquidação')
            ->assertSee('0,10')
            ->assertSee('Cliente Taxa Platform')
            ->assertSee('Transações com taxa platform');
    }

    public function test_guest_cannot_access_platform_revenue_page(): void
    {
        $this->get(route('dashboard.platform-revenue'))
            ->assertRedirect(route('login'));
    }

    public function test_transactions_filter_with_platform_fee(): void
    {
        $user = $this->userWithPermission('transactions.view');
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 0,
            'platform_fee' => 10,
            'net_amount' => 990,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.transactions', ['tab' => 'stripe', 'with_platform_fee' => 1]))
            ->assertOk()
            ->assertSee('Com taxa platform')
            ->assertSee('Carregamento');
    }

    public function test_platform_revenue_command_reports_totals(): void
    {
        $platform = $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        $transaction = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        LedgerEntry::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'direction' => 'credit',
            'amount' => 10,
            'balance_after' => 10,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT,
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'description' => PlatformRevenueService::LEDGER_PLATFORM_FEE_DESCRIPTION_PREFIX.$transaction->id,
        ]);

        $this->artisan('wallet:platform-revenue')
            ->assertSuccessful()
            ->expectsOutputToContain('Platform revenue report')
            ->expectsOutputToContain('Consistent');
    }

    public function test_wallet_audit_reports_platform_revenue_mismatch(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $this->artisan('wallet:audit', ['--dry-run' => true])
            ->assertFailed()
            ->expectsOutputToContain('PLATFORM_REVENUE_LEDGER_MISMATCH');
    }

    private function createPlatformCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-dashboard-test@yoop.com',
            'username' => 'platform_dashboard_test',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);
    }

    private function userWithPermission(string $permission): User
    {
        $permissionModel = Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo($permissionModel);

        return $user;
    }
}

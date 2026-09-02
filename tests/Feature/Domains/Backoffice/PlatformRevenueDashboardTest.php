<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\FundingPartnerMovement;
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
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer([
            'name' => 'Cliente Taxa Platform',
        ]);

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
            'description' => 'Top-up com taxa',
            'stripe_created_at' => now(),
        ]);

        $historical = $this->createRetailCustomer(['name' => 'Taxa Velha Ignorada']);
        StripeTransaction::query()->create([
            'customer_id' => $historical->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_100,
            'stripe_fee' => 61,
            'platform_fee' => 6,
            'net_amount' => 1_033,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'description' => 'Order velha',
            'stripe_created_at' => '2026-07-22 10:46:20',
        ]);

        $account = $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal_fx_page');
        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $account::class,
            'funding_account_id' => $account->id,
            'external_account_id' => 'wal_fx_page',
            'direction' => FundingPartnerMovementDirection::Out,
            'type' => FundingPartnerMovementType::CardPurchase,
            'amount' => 454,
            'currency' => 'EUR',
            'fees_amount' => 9,
            'platform_fees_amount' => 5,
            'status' => FundingPartnerMovementStatus::Completed,
            'source' => FundingPartnerMovementSource::Webhook,
            'idempotency_key' => 'card:fx-page:out',
            'partner_external_id' => 'fx-page',
            'description' => 'LidlGB',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.platform-revenue'))
            ->assertOk()
            ->assertSee('Receita Platform')
            ->assertSee('Liquidado')
            ->assertSee('Aguarda liquidação')
            ->assertSee('Câmbio Yoop')
            ->assertSee('faturação em todas as modalidades')
            ->assertSee('0,15')
            ->assertSee('0,10')
            ->assertSee('0,05')
            ->assertDontSee('0,16')
            ->assertSee('Cliente Taxa Platform')
            ->assertDontSee('Taxa Velha Ignorada')
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

        $this->artisan('wallet:platform-revenue')
            ->assertSuccessful()
            ->expectsOutputToContain('Platform revenue report')
            ->expectsOutputToContain('Total billed')
            ->expectsOutputToContain('FX Yoop');
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

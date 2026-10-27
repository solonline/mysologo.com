<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerBalanceAdjustment;
use App\Models\CustomerBalanceAdjustmentApproval;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class CustomerBalanceAdjustmentTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ACCOUNT_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('admin', 'web');

        $this->seedWallesterSettings();
        $this->seedOnboardingSettings();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_non_super_admin_cannot_request_balance_adjustment(): void
    {
        $user = User::factory()->create(['phone' => '351912345678']);
        $user->assignRole(Role::findByName('admin', 'web'));
        $user->givePermissionTo(Permission::findByName('customers.view', 'web'));

        $customer = $this->createRetailCustomer(['status' => CustomerLifecycleStatus::ACTIVE]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_customer');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Ajuste teste',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_without_phone_cannot_request_adjustment(): void
    {
        $user = $this->superAdmin(['phone' => null]);
        $customer = $this->prepareCustomerWithActiveWallester();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Ajuste teste',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('customer_balance_adjustments', 0);
    }

    public function test_request_blocked_when_wallester_not_active(): void
    {
        $user = $this->superAdmin();
        $customer = $this->createRetailCustomer(['status' => CustomerLifecycleStatus::ACTIVE]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING, 'wal_pending');
        $this->createPlatformMaster();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Ajuste teste',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('customer_balance_adjustments', 0);
    }

    public function test_request_sends_sms_and_creates_pending_adjustment(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '25.50',
                'description' => 'Crédito operacional',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_balance_adjustments', [
            'customer_id' => $customer->id,
            'direction' => CustomerBalanceAdjustmentDirection::Credit->value,
            'amount_cents' => 2550,
            'currency' => 'EUR',
            'status' => CustomerBalanceAdjustmentStatus::PendingApproval->value,
            'requested_by' => $user->id,
            'required_approvals' => 1,
        ]);

        $this->assertDatabaseHas('customer_balance_adjustment_approvals', [
            'user_id' => $user->id,
        ]);
    }

    public function test_request_returns_json_without_redirect_when_called_via_ajax(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $this->actingAs($user, 'web')
            ->postJson(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '25.50',
                'description' => 'Crédito operacional',
            ])
            ->assertOk()
            ->assertJsonPath('adjustment.direction', 'credit')
            ->assertJsonPath('adjustment.amount_cents', 2550)
            ->assertJsonPath('adjustment.status', CustomerBalanceAdjustmentStatus::PendingApproval->value);
    }

    public function test_confirm_with_valid_code_credits_customer_and_ledger(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();
        $this->createPlatformMaster();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['from_account_id'] === self::MASTER_ACCOUNT_ID
                    && $payload['to_account_id'] === 'wal_customer'
                    && abs($payload['amount'] - 10.00) < 0.001;
            }), Mockery::any())
            ->andReturn(['id' => 'pay_credit_1']);
        $this->expectWallesterBalanceSyncAfterMasterTransfer(
            $gateway,
            self::MASTER_ACCOUNT_ID,
            'wal_customer',
            $customer->id,
        );
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Crédito operacional',
            ])
            ->assertSessionHas('success');

        $adjustment = CustomerBalanceAdjustment::query()->firstOrFail();
        $approval = CustomerBalanceAdjustmentApproval::query()->firstOrFail();

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.confirm', [$customer, $adjustment]), [
                'code' => $approval->code,
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_balance_adjustments', [
            'id' => $adjustment->id,
            'status' => CustomerBalanceAdjustmentStatus::Executed->value,
            'partner_external_id' => 'pay_credit_1',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_CREDIT->value,
            'amount' => 1000,
            'currency' => 'EUR',
        ]);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertSame(1000, (int) $balance?->wallester_allocated);
        $this->assertSame(1000, (int) $balance?->wise_allocated);
    }

    public function test_confirm_debit_with_insufficient_ledger_fails(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();
        $this->createPlatformMaster();

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 500,
            'pending' => 0,
        ]);

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'debit',
                'amount' => '10.00',
                'description' => 'Débito operacional',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('customer_balance_adjustments', 0);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_confirm_with_invalid_code_does_not_execute(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();
        $this->createPlatformMaster();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')->never();
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Crédito operacional',
            ]);

        $adjustment = CustomerBalanceAdjustment::query()->firstOrFail();

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.confirm', [$customer, $adjustment]), [
                'code' => '000000',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customer_balance_adjustments', [
            'id' => $adjustment->id,
            'status' => CustomerBalanceAdjustmentStatus::PendingApproval->value,
        ]);

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_confirm_with_expired_code_does_not_execute(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();
        $this->createPlatformMaster();

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('accountTransfer')->never();
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'credit',
                'amount' => '10.00',
                'description' => 'Crédito operacional',
            ]);

        $adjustment = CustomerBalanceAdjustment::query()->firstOrFail();
        $approval = CustomerBalanceAdjustmentApproval::query()->firstOrFail();
        $approval->update(['code_expires_at' => now()->subMinute()]);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.balance-adjustments.confirm', [$customer, $adjustment]), [
                'code' => $approval->code,
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customer_balance_adjustments', [
            'id' => $adjustment->id,
            'status' => CustomerBalanceAdjustmentStatus::Expired->value,
        ]);
    }

    public function test_confirm_debit_executes_transfer_and_ledger(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();
        $this->createPlatformMaster();

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5000,
            'wise_allocated' => 5000,
            'pending' => 0,
        ]);

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['status' => 'success', 'body' => null, 'http_code' => 200]);
        });

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('wal_customer', (string) $customer->id)
            ->andReturn(['available_amount' => 50.00, 'balance' => 50.00]);
        $gateway->shouldReceive('accountTransfer')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['from_account_id'] === 'wal_customer'
                    && $payload['to_account_id'] === self::MASTER_ACCOUNT_ID
                    && abs($payload['amount'] - 10.00) < 0.001;
            }), Mockery::any())
            ->andReturn(['id' => 'pay_debit_1']);
        $this->expectWallesterBalanceSyncAfterMasterTransfer(
            $gateway,
            self::MASTER_ACCOUNT_ID,
            'wal_customer',
            $customer->id,
        );
        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.request', $customer), [
                'direction' => 'debit',
                'amount' => '10.00',
                'description' => 'Débito operacional',
            ])
            ->assertSessionHas('success');

        $adjustment = CustomerBalanceAdjustment::query()->firstOrFail();
        $approval = CustomerBalanceAdjustmentApproval::query()->firstOrFail();

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.balance-adjustments.confirm', [$customer, $adjustment]), [
                'code' => $approval->code,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_balance_adjustments', [
            'id' => $adjustment->id,
            'status' => CustomerBalanceAdjustmentStatus::Executed->value,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::MANUAL_DEBIT->value,
            'amount' => 1000,
        ]);

        $balance = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->where('currency', 'EUR')
            ->first();

        $this->assertSame(4000, (int) $balance?->wallester_allocated);
        $this->assertSame(4000, (int) $balance?->wise_allocated);
    }

    public function test_show_page_includes_credit_debit_for_super_admin_with_active_wallester(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Creditar')
            ->assertSee('Debitar');
    }

    public function test_show_page_displays_platform_adjustment_history_with_description(): void
    {
        $user = $this->superAdmin();
        $customer = $this->prepareCustomerWithActiveWallester();

        CustomerBalanceAdjustment::query()->create([
            'customer_id' => $customer->id,
            'direction' => CustomerBalanceAdjustmentDirection::Debit,
            'amount_cents' => 1250,
            'currency' => 'EUR',
            'description' => 'Ajuste manual do banco da plataforma',
            'status' => CustomerBalanceAdjustmentStatus::Executed,
            'required_approvals' => 1,
            'requested_by' => $user->id,
            'idempotency_key' => 'manual_adj:test-history',
            'partner_external_id' => 'pay_history_1',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Últimos Movimentos')
            ->assertDontSee('Histórico da Plataforma')
            ->assertSee('Débito Yoop')
            ->assertSee('Ajuste manual do banco da plataforma')
            ->assertSee('12,50');
    }

    /** @param array<string, mixed> $attributes */
    private function superAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'phone' => '351912345678',
        ], $attributes));
        $user->assignRole(Role::findByName('super-admin', 'web'));
        $user->givePermissionTo(Permission::findByName('customers.view', 'web'));

        return $user;
    }

    private function prepareCustomerWithActiveWallester(): Customer
    {
        $customer = $this->createRetailCustomer(['status' => CustomerLifecycleStatus::ACTIVE]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_customer');

        return $customer;
    }

    private function createPlatformMaster(): void
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
    }
}

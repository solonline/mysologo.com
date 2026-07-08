<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Backoffice\Actions\RetryAwaitingWallesterTopupsAction;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Jobs\FundWallesterTopupFromStripeJob;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class RetryAwaitingWallesterTopupsTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('settings.view');
        Permission::findOrCreate('settings.edit');
        $this->seedWallesterSettings();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_action_syncs_main_and_dispatches_retriable_topups(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $platform = $this->createRetailCustomer([
            'email' => 'master@yoop.com',
            'username' => 'platform',
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $main = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'available_amount' => 0,
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_retry_ops');

        $awaiting = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingLiquidity,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('master-eur-uuid', 'platform')
            ->andReturn([
                'balance' => 500.00,
                'available_amount' => 450.00,
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $result = app(RetryAwaitingWallesterTopupsAction::class)->execute();

        $this->assertSame(1, $result['synced_main']);
        $this->assertSame(1, $result['dispatched']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(45_000, (int) $main->fresh()->available_amount);

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class, function (FundWallesterTopupFromStripeJob $job) use ($awaiting): bool {
            return $job->stripeTransactionId === $awaiting->id;
        });
    }

    public function test_staff_can_trigger_retry_from_dashboard(): void
    {
        Bus::fake([FundWallesterTopupFromStripeJob::class]);

        $user = User::factory()->create();
        $user->givePermissionTo(['settings.view', 'settings.edit']);

        $platform = $this->createRetailCustomer([
            'email' => 'master@yoop.com',
            'username' => 'platform',
            'type' => 'platform',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $main = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'master-eur-uuid', [
            'is_main' => true,
            'currency_code' => 'EUR',
            'funding_currency' => 'EUR',
            'available_amount' => 0,
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_retry_ops');

        $awaiting = StripeTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 2_000,
            'stripe_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 2_000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingLiquidity,
        ]);

        $gateway = $this->mockWallesterGateway();
        $gateway->shouldReceive('getAccount')
            ->once()
            ->with('master-eur-uuid', 'platform')
            ->andReturn([
                'balance' => 500.00,
                'available_amount' => 450.00,
            ]);

        $this->app->instance(WallesterGatewayInterface::class, $gateway);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard.settings'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('dashboard.operations.retry-wallester-topups'))
            ->assertRedirect(route('dashboard.settings'))
            ->assertSessionHas('success');

        Bus::assertDispatched(FundWallesterTopupFromStripeJob::class);
    }

    public function test_guest_cannot_retry_wallester_topups(): void
    {
        $this->post(route('dashboard.operations.retry-wallester-topups'))
            ->assertRedirect(route('login'));
    }
}

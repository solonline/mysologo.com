<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\FundingPartnerMovement;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class DashboardIndexPlatformFeesTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    private const MASTER_ID = '75d92dc4-6de9-4580-8b62-3cbd3513af56';

    private const FEE_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();
    }

    public function test_dashboard_cards_show_sub_account_and_pending_platform_fees(): void
    {
        $this->seedWallesterSettings([
            'wallester.eur.platform_fee_account_id' => self::FEE_ID,
        ]);

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'dash-fee@yoop.test']);
        $retail = $this->createRetailCustomer();

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 125,
        ]);
        $fee = $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'available_amount' => 314,
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);

        $funded = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 369,
            'stripe_fee' => 34,
            'platform_fee' => 201,
            'net_amount' => 134,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::Completed,
            'partner_funded_at' => now(),
            'stripe_created_at' => now(),
        ]);
        $awaitingPayout = StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::MERCHANT_PAYMENT,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 246,
            'stripe_fee' => 33,
            'platform_fee' => 140,
            'net_amount' => 73,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'partner_funding_status' => PartnerFundingStatus::AwaitingStripePayout,
            'stripe_created_at' => now(),
        ]);

        $this->pendingFeeIn($fee, $funded, 201, ['pending_reason' => 'liquidity']);
        $this->pendingFeeIn($fee, $awaitingPayout, 140, ['pending_reason' => 'stripe_payout']);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('transactions.view', 'web'));

        $this->actingAs($user, 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sub-conta')
            ->assertSee('Lucro à espera de liquidez')
            ->assertSee('Lucro à espera Stripe')
            ->assertSee('Na sub-conta')
            ->assertSee('À espera de liquidez')
            ->assertSee('À espera Stripe')
            ->assertSee('Stripe')
            ->assertSee('3,41')
            ->assertSee('3,14')
            ->assertSee('2,01')
            ->assertSee('1,40');
    }

    public function test_wallester_card_shows_main_separately_from_accounts_total(): void
    {
        $this->seedWallesterSettings();

        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'dash-total@yoop.test']);
        $fee = Customer::factory()->create(['type' => 'platform', 'email' => 'dash-fee-total@yoop.test']);
        $retail = $this->createRetailCustomer(['email' => 'dash-retail-total@yoop.test']);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, self::MASTER_ID, [
            'is_main' => true,
            'available_amount' => 423,
        ]);
        $this->createWallesterAccount($fee, IntegrationAccountStatus::ACTIVE, self::FEE_ID, [
            'is_main' => false,
            'available_amount' => 518,
            'product_id' => 'prod_fee_'.$fee->id,
            'company_id' => 'comp_fee_'.$fee->id,
        ]);
        $this->createWallesterAccount($retail, IntegrationAccountStatus::ACTIVE, 'wal-dash-retail', [
            'available_amount' => 27093,
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $platform->id,
            'currency' => 'EUR',
            'wallester_allocated' => 423,
            'wise_allocated' => 0,
            'pending' => 0,
        ]);
        CustomerBalance::query()->create([
            'customer_id' => $fee->id,
            'currency' => 'EUR',
            'wallester_allocated' => 518,
            'wise_allocated' => 0,
            'pending' => 0,
        ]);
        CustomerBalance::query()->create([
            'customer_id' => $retail->id,
            'currency' => 'EUR',
            'wallester_allocated' => 27093,
            'wise_allocated' => 0,
            'pending' => 0,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wallester · Main')
            ->assertSee('4,23')
            ->assertSee('Contas')
            ->assertSee('Soma de todas as subcontas. A master fica de fora.')
            ->assertSee('276,11')
            ->assertDontSee('280,34');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function pendingFeeIn($feeAccount, StripeTransaction $transaction, int $amount, array $metadata): void
    {
        FundingPartnerMovement::query()->create([
            'provider' => IntegrationProvider::WALLESTER,
            'funding_account_type' => $feeAccount::class,
            'funding_account_id' => $feeAccount->id,
            'external_account_id' => $feeAccount->wallester_account_id,
            'direction' => FundingPartnerMovementDirection::In,
            'type' => FundingPartnerMovementType::AccountTransfer,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => FundingPartnerMovementStatus::Pending,
            'source' => FundingPartnerMovementSource::Api,
            'idempotency_key' => 'stripe-platform-fee:'.$transaction->id.':in',
            'description' => 'Taxa Yoop',
            'reference_type' => $transaction->getMorphClass(),
            'reference_id' => $transaction->id,
            'metadata' => $metadata,
        ]);
    }
}

<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Queries\GetWallesterFundingOverviewQuery;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class GetWallesterFundingOverviewQueryTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_sums_non_main_available_amount_by_currency(): void
    {
        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'wallester-total@yoop.test']);
        $retailA = $this->createRetailCustomer(['email' => 'retail-a-total@yoop.test']);
        $retailB = $this->createRetailCustomer(['email' => 'retail-b-total@yoop.test']);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'wal-main-eur', [
            'is_main' => true,
            'available_amount' => 423,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
        ]);
        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'wal-fee-eur', [
            'is_main' => false,
            'available_amount' => 518,
            'funding_currency' => 'EUR',
            'currency_code' => 'EUR',
            'product_id' => 'prod_fee_'.$platform->id,
            'company_id' => 'comp_fee_'.$platform->id,
        ]);
        $this->createWallesterAccount($retailA, IntegrationAccountStatus::ACTIVE, 'wal-retail-a', [
            'available_amount' => 27093,
        ]);
        $this->createWallesterAccount($retailB, IntegrationAccountStatus::ACTIVE, 'wal-retail-b', [
            'available_amount' => null,
        ]);

        $overview = app(GetWallesterFundingOverviewQuery::class)->execute();

        $this->assertCount(1, $overview['mainAccounts']);
        $this->assertSame(423, (int) $overview['mainAccounts']->first()->available_amount);
        $this->assertSame(27_611, (int) $overview['accountsAvailableByCurrency']->get('EUR'));
    }

    public function test_accounts_total_excludes_main_accounts(): void
    {
        $platform = Customer::factory()->create(['type' => 'platform', 'email' => 'wallester-main-only@yoop.test']);

        $this->createWallesterAccount($platform, IntegrationAccountStatus::ACTIVE, 'wal-main-only', [
            'is_main' => true,
            'available_amount' => 423,
        ]);

        $overview = app(GetWallesterFundingOverviewQuery::class)->execute();

        $this->assertSame(423, (int) $overview['mainAccounts']->first()->available_amount);
        $this->assertTrue($overview['accountsAvailableByCurrency']->isEmpty());
    }
}

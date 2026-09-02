<?php

namespace Tests\Feature\Database\Seeders;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Models\Customer;
use App\Models\CustomerStripeAccount;
use App\Models\Setting;
use Database\Seeders\PlatformSubMasterAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class PlatformSubMasterAccountSeederTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_seeder_creates_customer_and_wallester_account_without_stripe(): void
    {
        $platform = Customer::factory()->create([
            'id' => 1,
            'type' => 'platform',
            'email' => 'master@yoop.com',
            'name' => 'Yoop Platform',
            'username' => 'platform',
        ]);

        $this->createWallesterAccount(
            $platform,
            IntegrationAccountStatus::ACTIVE,
            '75d92dc4-6de9-4580-8b62-3cbd3513af56',
            [
                'is_main' => true,
                'funding_currency' => 'EUR',
                'product_id' => 'prod_platform_eur',
                'company_id' => 'comp_platform_eur',
            ],
        );

        $this->seed(PlatformSubMasterAccountSeeder::class);

        $this->assertDatabaseHas('customers', [
            'id' => PlatformSubMasterAccountSeeder::CUSTOMER_ID,
            'name' => PlatformSubMasterAccountSeeder::ACCOUNT_NAME,
            'email' => PlatformSubMasterAccountSeeder::CUSTOMER_EMAIL,
            'username' => PlatformSubMasterAccountSeeder::CUSTOMER_USERNAME,
            'type' => 'platform',
        ]);

        $this->assertDatabaseHas('customer_wallester_accounts', [
            'customer_id' => PlatformSubMasterAccountSeeder::CUSTOMER_ID,
            'wallester_account_id' => PlatformSubMasterAccountSeeder::WALLESTER_ACCOUNT_ID,
            'is_main' => false,
            'receiver_name' => PlatformSubMasterAccountSeeder::ACCOUNT_NAME,
            'funding_currency' => 'EUR',
        ]);

        $this->assertSame(
            0,
            CustomerStripeAccount::query()
                ->where('customer_id', PlatformSubMasterAccountSeeder::CUSTOMER_ID)
                ->count(),
        );
        $this->assertSame(
            PlatformSubMasterAccountSeeder::WALLESTER_ACCOUNT_ID,
            Setting::query()->where('key', 'wallester.eur.platform_fee_account_id')->value('value'),
        );
    }
}

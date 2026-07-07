<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Jobs\ProvisionFundingPartnersJob;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Wallester\Services\WallesterService;
use App\Models\CustomerStripeAccount;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class ProvisionFundingPartnersJobTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
        Mail::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view');
    }

    public function test_job_provisions_wallester_and_evaluates_activation(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'doc_status' => 'approved',
        ]);

        (new ProvisionFundingPartnersJob($customer->id))->handle(
            app(FinancialProvisioningService::class),
            app(CustomerLifecycleService::class),
        );

        $customer->refresh();
        $this->assertNotNull($customer->wallesterAccount);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $customer->wallesterAccount->status);
        $this->assertTrue(CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists());
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
    }

    public function test_job_failed_notifies_staff_and_keeps_customer_in_provisioning(): void
    {
        config(['services.provisioning.tries' => 1]);

        $staff = User::factory()->create();
        $staff->givePermissionTo('customers.view');

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'doc_status' => 'approved',
        ]);

        $this->mock(WallesterService::class, function ($mock) {
            $mock->shouldReceive('createAccount')
                ->andThrow(new \RuntimeException('Wallester API unavailable'));
        });

        $job = new ProvisionFundingPartnersJob($customer->id);

        try {
            $job->handle(
                app(FinancialProvisioningService::class),
                app(CustomerLifecycleService::class),
            );
            $this->fail('Expected provisioning to fail.');
        } catch (\RuntimeException $exception) {
            $job->failed($exception);
        }

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::PROVISIONING, $customer->status);
        $this->assertSame(IntegrationAccountStatus::FAILED, $customer->wallesterAccount->status);
        $this->assertFalse(CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists());
        $this->assertTrue(
            UserNotification::query()->where('user_id', $staff->id)->where('type', 'provisioning.wallester_failed')->exists(),
        );
    }
}

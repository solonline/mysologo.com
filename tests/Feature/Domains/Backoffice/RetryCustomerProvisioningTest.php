<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Jobs\ProvisionFundingPartnersJob;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class RetryCustomerProvisioningTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view');
        Permission::findOrCreate('customers.edit');
    }

    public function test_staff_can_retry_provisioning_for_failed_wallester_account(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->givePermissionTo(['customers.view', 'customers.edit']);

        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'doc_status' => 'approved',
        ]);

        $this->createWallesterAccount($customer, IntegrationAccountStatus::FAILED, 'pending:'.$customer->id);

        $this->actingAs($user)
            ->post(route('dashboard.customers.retry-provisioning', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ProvisionFundingPartnersJob::class, function (ProvisionFundingPartnersJob $job) use ($customer) {
            return $job->customerId === $customer->id;
        });

        $this->assertSame(
            IntegrationAccountStatus::PENDING,
            $customer->fresh()->wallesterAccount->status,
        );
    }
}

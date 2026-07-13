<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Contracts\StripeGatewayInterface;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class RetryStripeFundingTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view');
        Permission::findOrCreate('customers.edit');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_staff_can_retry_stripe_funding_for_failed_account(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['customers.view', 'customers.edit']);

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_retry_funding', [
            'funding_status' => StripeFundingStatus::FAILED,
            'funding_status_reason' => 'Stripe API unavailable',
        ]);

        $this->mock(StripeGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCustomerFundingInstructions')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'message' => null,
                    'data' => $this->sampleStripeFundingInstructionsPayload(),
                ]);
        });

        $this->actingAs($user)
            ->post(route('dashboard.customers.retry-stripe-funding', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = $customer->fresh()->stripeAccount;
        $this->assertSame(StripeFundingStatus::ACTIVE, $account->funding_status);
        $this->assertSame('DE89370400440532013000', $account->funding_iban);
        $this->assertNull($account->funding_status_reason);
    }

    public function test_staff_cannot_retry_when_funding_is_already_active(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['customers.view', 'customers.edit']);

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_active_funding', [
            'funding_status' => StripeFundingStatus::ACTIVE,
            'funding_iban' => 'DE89370400440532013000',
        ]);

        $this->actingAs($user)
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.retry-stripe-funding', $customer))
            ->assertRedirect(route('dashboard.customers.show', $customer))
            ->assertSessionHasErrors('customer');
    }

    public function test_staff_without_edit_permission_cannot_retry_stripe_funding(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('customers.view');

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_forbidden', [
            'funding_status' => StripeFundingStatus::FAILED,
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.customers.retry-stripe-funding', $customer))
            ->assertForbidden();
    }
}

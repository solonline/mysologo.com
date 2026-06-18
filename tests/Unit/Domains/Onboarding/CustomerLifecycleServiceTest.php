<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Onboarding\Events\CustomerApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerLifecycleServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    private CustomerLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lifecycle = app(CustomerLifecycleService::class);
    }

    public function test_initial_status_for_registration_is_pending_email_verification(): void
    {
        $this->assertSame(
            CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
            $this->lifecycle->initialStatusForRegistration(),
        );
    }

    public function test_blocks_customer_when_both_funding_partners_are_rejected(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::FAILED);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::FAILED);

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::BLOCKED, $customer->status);
        $this->assertStringContainsString('Ebury e Wallester', (string) $customer->status_reason);
    }

    public function test_stays_provisioning_when_no_partner_is_approved(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING);

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::PROVISIONING, $customer->status);
    }

    public function test_activates_when_wallester_and_stripe_are_ready(): void
    {
        Event::fake([CustomerApproved::class]);

        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        Event::assertDispatched(CustomerApproved::class);
    }

    public function test_activates_when_ebury_and_stripe_are_ready(): void
    {
        Event::fake([CustomerApproved::class]);

        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        Event::assertDispatched(CustomerApproved::class);
    }

    public function test_ebury_rejection_does_not_block_when_wallester_is_active(): void
    {
        Event::fake([CustomerApproved::class]);

        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::FAILED);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        Event::assertDispatched(CustomerApproved::class);
    }

    public function test_provisions_stripe_when_partner_is_approved(): void
    {
        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->mock(FinancialProvisioningService::class, function ($mock) use ($customer) {
            $mock->shouldReceive('provisionStripe')
                ->once()
                ->withArgs(fn ($arg) => $arg->id === $customer->id)
                ->andReturn(false);
        });

        $this->lifecycle->evaluateActivation($customer);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::PROVISIONING, $customer->status);
        $this->assertStringContainsString('Stripe', (string) $customer->status_reason);
    }

    public function test_has_approved_funding_partner_with_either_integration(): void
    {
        $customer = $this->createRetailCustomer();

        $this->assertFalse($this->lifecycle->hasApprovedFundingPartner($customer));

        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $customer->refresh();

        $this->assertTrue($this->lifecycle->hasApprovedFundingPartner($customer));
    }
}

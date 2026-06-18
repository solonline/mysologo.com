<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class FinancialProvisioningServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_provision_ebury_creates_account_and_marks_in_review_without_auto_approve(): void
    {
        config(['services.ebury.simulate_auto_approve' => false]);

        $customer = $this->createRetailCustomer();
        $service = app(FinancialProvisioningService::class);

        $this->assertTrue($service->provisionEbury($customer));

        $customer->refresh();

        $this->assertNotNull($customer->eburyAccount);
        $this->assertSame('sim:' . $customer->id, $customer->eburyAccount->ebury_account_id);
        $this->assertSame(IntegrationAccountStatus::PENDING, $customer->eburyAccount->status);
    }

    public function test_provision_ebury_auto_approve_activates_when_wallester_is_ready(): void
    {
        config([
            'services.ebury.simulate_auto_approve' => true,
            'services.ebury.simulate_use_queue' => false,
        ]);

        $this->mockFinancialPartnerApis();

        $customer = $this->createRetailCustomer();
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);

        app(FinancialProvisioningService::class)->provisionEbury($customer);

        $customer->refresh();

        $this->assertSame(IntegrationAccountStatus::ACTIVE, $customer->eburyAccount->status);
        $this->assertTrue(
            \App\Models\CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists(),
        );
    }

    public function test_provision_ebury_is_idempotent_when_already_submitted(): void
    {
        config(['services.ebury.simulate_auto_approve' => false]);

        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING, 'sim:' . $customer->id);

        $this->mock(\App\Domains\Ebury\Contracts\EburyGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('submitCustomer')->never();
        });

        $this->assertTrue(app(FinancialProvisioningService::class)->provisionEbury($customer));
    }
}

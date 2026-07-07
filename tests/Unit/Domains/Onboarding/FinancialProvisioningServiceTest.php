<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Ebury\Contracts\EburyGatewayInterface;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Wallester\Services\WallesterService;
use App\Models\CustomerStripeAccount;
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
        $this->assertSame('sim:'.$customer->id, $customer->eburyAccount->ebury_account_id);
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
            CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists(),
        );
    }

    public function test_provision_ebury_is_idempotent_when_already_submitted(): void
    {
        config(['services.ebury.simulate_auto_approve' => false]);

        $customer = $this->createRetailCustomer();
        $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING, 'sim:'.$customer->id);

        $this->mock(EburyGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('submitCustomer')->never();
        });

        $this->assertTrue(app(FinancialProvisioningService::class)->provisionEbury($customer));
    }

    public function test_provision_wallester_creates_pending_row_before_api_call(): void
    {
        $customer = $this->createRetailCustomer();
        $this->mock(WallesterService::class, function ($mock) {
            $mock->shouldReceive('createAccount')
                ->once()
                ->andReturn(['id' => 'wal_api_test']);
        });

        $this->assertTrue(app(FinancialProvisioningService::class)->provisionWallester($customer));

        $account = $customer->fresh()->wallesterAccount;
        $this->assertNotNull($account);
        $this->assertSame('wal_api_test', $account->wallester_account_id);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $account->status);
    }

    public function test_provision_wallester_marks_failed_when_api_errors(): void
    {
        $customer = $this->createRetailCustomer();
        $this->mock(WallesterService::class, function ($mock) {
            $mock->shouldReceive('createAccount')
                ->once()
                ->andThrow(new \RuntimeException('Wallester API unavailable'));
        });

        $this->assertFalse(app(FinancialProvisioningService::class)->provisionWallester($customer));

        $account = $customer->fresh()->wallesterAccount;
        $this->assertNotNull($account);
        $this->assertSame(IntegrationAccountStatus::FAILED, $account->status);
        $this->assertSame('Wallester API unavailable', $account->status_reason);
    }
}

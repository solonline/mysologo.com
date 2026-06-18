<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class WallesterWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_rejected_webhook_blocks_when_ebury_is_also_rejected(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $this->createEburyAccount($customer, IntegrationAccountStatus::FAILED);
        $wallester = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->postJson('/api/webhooks/wallester', [
            'account_id' => $wallester->wallester_account_id,
            'status' => 'rejected',
            'reason' => 'Recusado pela Wallester',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::BLOCKED, $customer->status);
        $this->assertSame(IntegrationAccountStatus::FAILED, $wallester->fresh()->status);
    }

    public function test_active_webhook_keeps_customer_active_when_stripe_is_ready(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $this->createEburyAccount($customer, IntegrationAccountStatus::FAILED);
        $wallester = $this->createWallesterAccount($customer, IntegrationAccountStatus::PENDING);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->postJson('/api/webhooks/wallester', [
            'account_id' => $wallester->wallester_account_id,
            'status' => 'active',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $wallester->fresh()->status);
    }
}

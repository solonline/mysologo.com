<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class EburyWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    public function test_approved_webhook_activates_customer_when_wallester_and_stripe_are_ready(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $account = $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $response = $this->postJson('/api/webhooks/ebury', [
            'account_id' => $account->ebury_account_id,
            'status' => 'approved',
            'reason' => 'Conta aprovada',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $account->fresh()->status);
    }

    public function test_rejected_webhook_does_not_block_when_wallester_is_active(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $account = $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->postJson('/api/webhooks/ebury', [
            'account_id' => $account->ebury_account_id,
            'status' => 'rejected',
            'reason' => 'Recusado pela Ebury',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame(IntegrationAccountStatus::FAILED, $account->fresh()->status);
    }
}

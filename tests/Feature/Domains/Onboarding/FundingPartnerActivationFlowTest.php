<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Services\WallesterService;
use App\Models\CustomerStripeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class FundingPartnerActivationFlowTest extends TestCase
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

    public function test_kyc_to_active_via_wallester(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_flow_1');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($customer->id, $session->session_id),
        );

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertTrue(CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists());
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $customer->wallesterAccount->status);
    }

    public function test_kyc_to_active_when_ebury_rejects_but_wallester_is_active(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_flow_1b');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($customer->id, $session->session_id),
        );

        $customer->refresh();
        $ebury = $this->createEburyAccount($customer, IntegrationAccountStatus::ACTIVE, 'sim:'.$customer->id);

        $this->postJson('/api/webhooks/ebury', [
            'account_id' => $ebury->ebury_account_id,
            'status' => 'rejected',
            'reason' => 'Recusado pela Ebury',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame(IntegrationAccountStatus::FAILED, $ebury->fresh()->status);
    }

    public function test_kyc_to_blocked_when_both_partners_reject(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_flow_2');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($customer->id, $session->session_id),
        );

        $customer->refresh();
        $wallester = $customer->wallesterAccount;
        $this->assertNotNull($wallester);
        $ebury = $this->createEburyAccount($customer, IntegrationAccountStatus::ACTIVE, 'sim:'.$customer->id);

        $this->postJson('/api/webhooks/ebury', [
            'account_id' => $ebury->ebury_account_id,
            'status' => 'rejected',
        ])->assertOk();

        $this->postJson('/api/webhooks/wallester', [
            'account_id' => $wallester->wallester_account_id,
            'status' => 'rejected',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::BLOCKED, $customer->status);
    }

    public function test_stripe_is_not_created_before_a_funding_partner_is_approved(): void
    {
        $this->mock(WallesterService::class, function ($mock) {
            $mock->shouldReceive('createAccount')
                ->andThrow(new \RuntimeException('Wallester unavailable'));
        });

        config(['services.provisioning.tries' => 1]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_flow_3');

        try {
            app(IdentityVerificationWebhookService::class)->handleDataUpdated(
                $this->diditApprovedPayload($customer->id, $session->session_id),
            );
        } catch (\RuntimeException) {
            // Sync queue exhausts retries and rethrows after marking FAILED.
        }

        $this->assertFalse(
            CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists(),
        );
        $this->assertSame(IntegrationAccountStatus::FAILED, $customer->fresh()->wallesterAccount?->status);
    }
}

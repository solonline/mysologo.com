<?php

namespace Tests\Feature\Domains\Onboarding;

use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Models\CustomerStripeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
    }

    public function test_kyc_to_active_via_wallester_when_ebury_rejects(): void
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

        $ebury = $customer->eburyAccount;
        $this->assertNotNull($ebury);

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
        $ebury = $customer->eburyAccount;
        $wallester = $customer->wallesterAccounts;
        $this->assertNotNull($ebury);
        $this->assertNotNull($wallester);

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
        config(['services.ebury.simulate_auto_approve' => false]);

        $this->mock(\App\Domains\Onboarding\Services\FinancialProvisioningService::class, function ($mock) {
            $mock->shouldReceive('provisionPostKycPartners')->once();
        });

        $this->mock(\App\Domains\Stripe\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->never();
        });

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_flow_3');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($customer->id, $session->session_id),
        );

        $this->assertFalse(
            CustomerStripeAccount::query()->where('customer_id', $customer->id)->exists(),
        );
    }
}

<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class IntegrationAccountServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    private IntegrationAccountService $integrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrations = app(IntegrationAccountService::class);
    }

    public function test_ebury_in_review_moves_lifecycle_to_under_review(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $account = $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING);

        $this->integrations->applyEburyExternalStatus(
            $account,
            'in_review',
            'Em análise',
            'ebury.test',
        );

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::EBURY_UNDER_REVIEW, $customer->status);
    }

    public function test_ebury_approval_triggers_activation_when_stripe_ready(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $account = $this->createEburyAccount($customer, IntegrationAccountStatus::PENDING);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->integrations->applyEburyExternalStatus(
            $account,
            'approved',
            'Aprovado',
            'ebury.test',
        );

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame(IntegrationAccountStatus::ACTIVE, $account->fresh()->status);
    }

    public function test_capabilities_do_not_block_all_transactions_when_only_ebury_is_missing(): void
    {
        $customer = $this->createRetailCustomer(['status' => CustomerLifecycleStatus::ACTIVE]);
        $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $capabilities = $this->integrations->resolveCapabilities($customer->fresh());

        $this->assertTrue($capabilities['can_transact']);
        $mandatoryEburyBlockers = array_filter(
            $capabilities['blockers'],
            fn (array $blocker) => ($blocker['scope'] ?? '') === 'ebury' && ($blocker['mandatory'] ?? false),
        );
        $this->assertEmpty($mandatoryEburyBlockers);
    }

    public function test_wallester_rejection_syncs_lifecycle_evaluation(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
        $ebury = $this->createEburyAccount($customer, IntegrationAccountStatus::FAILED);
        $wallester = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->integrations->applyWallesterExternalStatus(
            $wallester,
            'rejected',
            'Recusado',
            'wallester.test',
        );

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::BLOCKED, $customer->status);
        $this->assertSame(IntegrationAccountStatus::FAILED, $ebury->fresh()->status);
    }

    public function test_handle_stripe_funding_instructions_created_persists_iban_details(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->integrations->handleStripeFundingInstructionsCreated(
            $account,
            $this->sampleStripeFundingInstructionsPayload(),
        );

        $account->refresh();

        $this->assertSame(StripeFundingStatus::ACTIVE, $account->funding_status);
        $this->assertSame('DE89370400440532013000', $account->funding_iban);
        $this->assertSame('COBADEFFXXX', $account->funding_bic);
        $this->assertSame('Test User', $account->funding_account_holder_name);
        $this->assertSame('DE', $account->funding_country);
        $this->assertSame('bank_transfer', $account->funding_type);
        $this->assertSame('eu_bank_transfer', $account->bank_transfer_type);
    }

    public function test_mark_stripe_funding_failed_persists_reason(): void
    {
        $customer = $this->createRetailCustomer();
        $account = $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE);

        $this->integrations->markStripeFundingFailed($account, 'Stripe API unavailable');

        $account->refresh();

        $this->assertSame(StripeFundingStatus::FAILED, $account->funding_status);
        $this->assertSame('Stripe API unavailable', $account->funding_status_reason);
        $this->assertNotNull($account->funding_status_changed_at);
    }
}

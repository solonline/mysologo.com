<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerWallesterAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class IdentityVerificationWebhookTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
        Mail::fake();
    }

    public function test_approved_decision_submits_ebury_and_provisions_wallester(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_kyc_1');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($customer->id, $session->session_id),
        );

        $customer->refresh();

        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
        $this->assertTrue(CustomerEburyAccount::query()->where('customer_id', $customer->id)->exists());
        $this->assertTrue(CustomerWallesterAccount::query()->where('customer_id', $customer->id)->exists());
    }

    public function test_declined_decision_marks_identity_rejected(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_kyc_2');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated([
            'session_id' => $session->session_id,
            'decision' => [
                'status' => 'Declined',
                'id_verifications' => [
                    ['document_number' => 'DOC999'],
                ],
            ],
        ]);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
    }

    public function test_didit_webhook_endpoint_dispatches_processing_job(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_kyc_3');

        $response = $this->postJson('/api/webhooks/didit', $this->diditApprovedPayload(
            $customer->id,
            $session->session_id,
            'DOC777',
        ));

        $response->assertOk()->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
    }
}

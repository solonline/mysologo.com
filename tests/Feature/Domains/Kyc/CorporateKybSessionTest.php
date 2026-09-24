<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\DiditVendorData;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerCorporateDocument;
use App\Models\CustomerDiditSession;
use App\Models\CustomerEburyAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CorporateKybSessionTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
        Mail::fake();
        Queue::fake();
    }

    public function test_corporate_kyb_session_uses_kyb_workflow_after_responsible_kyc_approved(): void
    {
        Http::fake([
            'https://didit.test/session' => Http::response([
                'session_id' => 'sess_kyb_1',
                'url' => 'https://didit.test/kyb/session',
                'status' => 'Not Started',
                'workflow_id' => 'wf_kyb_test',
            ], 201),
        ]);

        [$corporate, $responsible] = $this->createCorporateWithApprovedResponsible();

        $token = $corporate->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/kyc/identity/session');

        $response->assertOk();

        $this->assertDatabaseHas('customer_didit_sessions', [
            'customer_id' => $corporate->id,
            'session_id' => 'sess_kyb_1',
            'workflow_id' => 'wf_kyb_test',
        ]);

        $this->assertDatabaseHas('customer_corporate_documents', [
            'customer_id' => $corporate->id,
            'status' => 'Pending',
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        Http::assertSent(function ($request) use ($corporate) {
            $payload = $request->data();

            return $request->url() === 'https://didit.test/session'
                && $request['workflow_id'] === 'wf_kyb_test'
                && ($payload['vendor_data'] ?? null) === DiditVendorData::forCustomer($corporate->id)
                && ! array_key_exists('customer_id', $payload);
        });

        $session = CustomerDiditSession::query()->where('session_id', 'sess_kyb_1')->first();
        $this->assertNotNull($session);
        $this->assertSame(DiditVendorData::forCustomer($corporate->id), $session->metadata['vendor_data'] ?? null);

        $this->assertTrue($responsible->is($corporate->fresh()->responsibles()->first()));
    }

    public function test_corporate_kyb_session_blocked_when_responsible_not_approved(): void
    {
        $corporate = Customer::factory()->create([
            'type' => 'corporate',
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
        ]);

        Customer::factory()->create([
            'type' => 'responsible',
            'corporate_id' => $corporate->id,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        $result = app(IdentityVerificationService::class)->ensureValidCorporateKyB($corporate);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['session']);
        Http::assertNothingSent();
    }

    public function test_kyb_webhook_approval_marks_corporate_document_valid_without_ebury(): void
    {
        [$corporate] = $this->createCorporateWithApprovedResponsible();

        $document = CustomerCorporateDocument::query()->create([
            'customer_id' => $corporate->id,
            'legal_name' => 'Acme Corp',
            'country_code' => 'PT',
            'tax_id' => 'kyb_'.$corporate->id,
            'document_type' => 'NIF',
            'status' => 'Pending',
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        $session = CustomerDiditSession::query()->create([
            'customer_id' => $corporate->id,
            'session_id' => 'sess_kyb_webhook',
            'status' => 'In Review',
            'workflow_id' => 'wf_kyb_test',
            'session_url' => 'https://didit.test/kyb',
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditApprovedPayload($corporate->id, $session->session_id, 'KYB123'),
        );

        $corporate->refresh();
        $document->refresh();

        $this->assertSame('Valid', $document->status);
        $this->assertSame(DocumentStatus::APPROVED->value, $document->doc_status);
        $this->assertSame(DocumentStatus::APPROVED->value, $corporate->doc_status);
        $this->assertFalse(CustomerEburyAccount::query()->where('customer_id', $corporate->id)->exists());
    }

    /** @return array{0: Customer, 1: Customer} */
    private function createCorporateWithApprovedResponsible(): array
    {
        $corporate = Customer::factory()->create([
            'type' => 'corporate',
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
        ]);

        $responsible = Customer::factory()->create([
            'type' => 'responsible',
            'corporate_id' => $corporate->id,
            'doc_status' => DocumentStatus::APPROVED->value,
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);

        return [$corporate, $responsible];
    }
}

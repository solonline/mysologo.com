<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Jobs\UploadDocumentMediaJob;
use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\CustomerDiditSession;
use App\Models\CustomerWallesterAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake([UploadDocumentMediaJob::class]);
    }

    public function test_approved_decision_dispatches_provisioning_and_creates_wallester(): void
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
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
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

        $response = $this->postDiditWebhook($this->diditApprovedPayload(
            $customer->id,
            $session->session_id,
            'DOC777',
        ));

        $response->assertOk()->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
    }

    public function test_user_data_updated_declined_via_application_id(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $vendorUserId = '9342433c-a69d-42f5-9dd2-5ab079051b89';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, $applicationId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined'),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
        $this->assertSame('Declined', $session->status);
    }

    public function test_user_data_updated_finds_session_by_metadata_application_id(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $vendorUserId = '9342433c-a69d-42f5-9dd2-5ab079051b89';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => 'didit-internal-session-id',
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-metadata',
            'metadata' => ['application_id' => $applicationId],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined'),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
    }

    public function test_user_data_updated_ignores_unknown_application_id(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload(
                'unknown-application-id',
                'vendor-unknown',
                'Declined',
            ),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $customer->status);
        $this->assertNull(CustomerDiditSession::query()->where('customer_id', $customer->id)->first());
    }

    public function test_user_data_updated_approved_provisions_partners(): void
    {
        $applicationId = 'app-approved-001';
        $vendorUserId = 'vendor-approved-001';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $applicationId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Approved'),
        );

        $customer->refresh();

        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
        $this->assertTrue(CustomerWallesterAccount::query()->where('customer_id', $customer->id)->exists());
    }

    public function test_didit_webhook_endpoint_processes_user_data_updated_payload(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $vendorUserId = '9342433c-a69d-42f5-9dd2-5ab079051b89';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $applicationId);

        $response = $this->postDiditWebhook(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined'),
        );

        $response->assertOk()->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
    }

    public function test_status_updated_declined_with_null_document_number(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'Declined', [
                'id_verifications' => [
                    [
                        'document_number' => null,
                        'document_type' => 'Identity Card',
                        'status' => 'Declined',
                        'portrait_image' => 'https://didit.test/portrait.jpg',
                    ],
                ],
            ]),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
        $this->assertDatabaseHas('customer_retail_documents', [
            'customer_id' => $customer->id,
            'document_number' => 'didit:'.$sessionId,
        ]);
    }

    public function test_status_updated_not_started_without_id_verifications(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '1760a174-62c5-40a1-be27-89f81a1accb2';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'Not Started'),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame(CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION, $customer->status);
        $this->assertSame(DocumentStatus::PENDING->value, $customer->doc_status);
        $this->assertSame('Not Started', $session->status);
    }

    public function test_status_updated_finds_session_by_application_id_metadata(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => $sessionId,
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-status',
            'metadata' => ['application_id' => $applicationId],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'Declined'),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
    }

    public function test_status_updated_syncs_customer_name_from_id_verification(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'name' => 'Old Name',
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'In Review', [
                'id_verifications' => [
                    [
                        'document_number' => 'DOC123',
                        'full_name' => 'Mario Rossi',
                        'status' => 'In Review',
                    ],
                ],
            ]),
        );

        $customer->refresh();

        $this->assertSame('Mario Rossi', $customer->name);
    }

    public function test_status_updated_does_not_clear_customer_name_when_ocr_empty(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'name' => 'Preserved Name',
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'Declined', [
                'id_verifications' => [
                    [
                        'document_number' => null,
                        'first_name' => null,
                        'last_name' => null,
                        'full_name' => null,
                        'status' => 'Declined',
                    ],
                ],
            ]),
        );

        $customer->refresh();

        $this->assertSame('Preserved Name', $customer->name);
    }

    public function test_user_data_updated_with_decision_delegates_to_status_handler(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'name' => 'Before KYC',
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated([
            'webhook_type' => 'user.data.updated',
            'application_id' => $applicationId,
            'session_id' => $sessionId,
            'status' => 'Declined',
            'decision' => [
                'session_id' => $sessionId,
                'status' => 'Declined',
                'id_verifications' => [
                    [
                        'document_number' => 'DOC555',
                        'full_name' => 'Anna Verdi',
                        'status' => 'Declined',
                    ],
                ],
            ],
        ]);

        $customer->refresh();

        $this->assertSame('Anna Verdi', $customer->name);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
    }

    public function test_status_updated_declined_real_payload_persists_warnings_and_media(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'name' => 'Mario Rossi',
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditRealDeclinedStatusPayload($applicationId, $sessionId),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
        $this->assertSame('Mario Rossi', $customer->name);
        $this->assertSame('Declined', $session->status);
        $this->assertSame($applicationId, $session->metadata['application_id']);
        $this->assertSame($sessionId, $session->metadata['session_id']);

        $document = $customer->documents()->where('document_number', 'didit:'.$sessionId)->first();
        $this->assertNotNull($document);
        $this->assertSame('ITA', $document->issuing_state);
        $this->assertSame('Identity Card', $document->document_type);
        $this->assertIsArray($document->extra_fields['warnings'] ?? null);
        $this->assertSame('SCREEN_CAPTURE_DETECTED', $document->extra_fields['warnings'][0]['risk']);
        $this->assertSame('Declined', $document->extra_fields['status']);

        Queue::assertPushed(UploadDocumentMediaJob::class);
    }
}

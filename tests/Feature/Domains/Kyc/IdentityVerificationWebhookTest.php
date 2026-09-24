<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\DiditVendorData;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Jobs\UploadDocumentMediaJob;
use App\Domains\Kyc\Mail\DocumentStatusMail;
use App\Domains\Kyc\Services\IdentityVerificationWebhookService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
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

        $this->runProvisionFundingPartnersJob($customer->fresh());

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
        $payload = $this->diditApprovedPayload(
            $customer->id,
            $session->session_id,
            'DOC777',
        );

        $response = $this->postDiditWebhook($payload);

        $response->assertOk()->assertJson(['success' => true]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated($payload);

        $customer->refresh();
        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
    }

    public function test_didit_webhook_endpoint_rejects_invalid_v2_signature(): void
    {
        $payload = $this->diditApprovedPayload(1, 'sess_kyc_sig');

        $response = $this->postDiditWebhook($payload, [
            'X-Signature-V2' => str_repeat('a', 64),
        ]);

        $response->assertUnauthorized()->assertJson(['error' => 'Invalid signature']);
    }

    public function test_didit_webhook_endpoint_rejects_stale_timestamp(): void
    {
        $payload = $this->diditApprovedPayload(1, 'sess_kyc_stale');

        $response = $this->postDiditWebhook($payload, [
            'X-Timestamp' => (string) now()->subMinutes(10)->timestamp,
        ]);

        $response->assertUnauthorized()->assertJson(['error' => 'Invalid signature']);
    }

    public function test_didit_webhook_endpoint_accepts_raw_x_signature_fallback(): void
    {
        $payload = $this->diditApprovedPayload(1, 'sess_kyc_raw');
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->withHeaders([
            'X-Signature-V2' => str_repeat('b', 64),
            'X-Signature' => hash_hmac('sha256', $raw, 'didit-webhook-secret'),
            'X-Timestamp' => (string) now()->timestamp,
        ])->postJson('/api/webhooks/didit', $payload);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_user_data_updated_declined_via_vendor_data(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $vendorUserId = '9342433c-a69d-42f5-9dd2-5ab079051b89';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $session = $this->createDiditSession($customer, 'sess_vendor_data_1');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined', [
                'vendor_data' => DiditVendorData::forCustomer($customer->id),
            ]),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
        $this->assertSame('Declined', $session->status);
    }

    public function test_user_data_updated_finds_session_by_vendor_user_id(): void
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
            'metadata' => ['vendor_user_id' => $vendorUserId],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined'),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $customer->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $customer->doc_status);
    }

    public function test_user_data_updated_ignores_unknown_vendor_ids(): void
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
        $this->createDiditSession($customer, 'sess-approved-001');

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Approved', [
                'vendor_data' => DiditVendorData::forCustomer($customer->id),
            ]),
        );

        $this->runProvisionFundingPartnersJob($customer->fresh());

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
        $this->createDiditSession($customer, 'sess_webhook_endpoint_1');
        $payload = $this->diditUserDataUpdatedPayload($applicationId, $vendorUserId, 'Declined', [
            'vendor_data' => DiditVendorData::forCustomer($customer->id),
        ]);

        $response = $this->postDiditWebhook($payload);

        $response->assertOk()->assertJson(['success' => true]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated($payload);

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
        Mail::assertNotSent(DocumentStatusMail::class);
    }

    public function test_in_progress_session_does_not_send_document_status_email(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '1760a174-62c5-40a1-be27-89f81a1accb2';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
            'doc_status' => DocumentStatus::PENDING->value,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'In Progress'),
        );

        Mail::assertNotSent(DocumentStatusMail::class);
    }

    public function test_in_review_sends_pending_document_status_email(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::PENDING->value,
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

        Mail::assertSent(DocumentStatusMail::class, function (DocumentStatusMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->email) && $mail->status === 'pending';
        });
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

    public function test_status_updated_does_not_apply_decision_to_wrong_customer_when_application_id_collides(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = '9e06e806-4b55-4b79-b7fa-792287ae7ed8';

        $targetCustomer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $otherCustomer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $targetCustomer->id,
            'session_id' => $sessionId,
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-target',
            'metadata' => ['application_id' => $applicationId],
        ]);

        $this->createDiditSession($otherCustomer, $applicationId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'Approved'),
        );

        $targetCustomer->refresh();
        $otherCustomer->refresh();

        $this->assertSame(DocumentStatus::APPROVED->value, $targetCustomer->doc_status);
        $this->assertTrue(in_array($targetCustomer->status, [
            CustomerLifecycleStatus::PROVISIONING,
            CustomerLifecycleStatus::ACTIVE,
        ], true));
        $this->assertSame('pending', $otherCustomer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $otherCustomer->status);
    }

    public function test_status_updated_scopes_retail_document_to_matching_customer(): void
    {
        $applicationId = 'app-doc-scope-001';
        $sessionId = 'sess-doc-scope-001';

        $customerA = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);
        $customerB = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        ]);

        $this->createDiditSession($customerA, $sessionId);

        CustomerRetailDocument::query()->create([
            'customer_id' => $customerB->id,
            'document_number' => 'DOC123',
            'document_type' => 'Identity Card',
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $sessionId, 'In Review', [
                'id_verifications' => [
                    [
                        'document_number' => 'DOC123',
                        'full_name' => 'Correct Customer',
                        'status' => 'In Review',
                    ],
                ],
            ]),
        );

        $customerA->refresh();
        $customerB->refresh();

        $this->assertSame('Correct Customer', $customerA->name);
        $this->assertNotSame('Correct Customer', $customerB->name);
        $this->assertDatabaseHas('customer_retail_documents', [
            'customer_id' => $customerA->id,
            'document_number' => 'DOC123',
            'full_name' => 'Correct Customer',
        ]);
        $this->assertDatabaseMissing('customer_retail_documents', [
            'customer_id' => $customerB->id,
            'document_number' => 'DOC123',
            'full_name' => 'Correct Customer',
        ]);
    }

    public function test_expired_session_does_not_send_document_status_email(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = 'd54203d5-1dee-44c8-bbdb-ebe1fc1418ef';
        $webhookSessionId = 'fdd2191b-ed54-411b-90f7-356265ff865f';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => $sessionId,
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-expired',
            'metadata' => [
                'application_id' => $applicationId,
                'session_id' => $webhookSessionId,
            ],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $webhookSessionId, 'Expired'),
        );

        $customer->refresh();

        $this->assertSame(DocumentStatus::ANALYSING->value, $customer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $customer->status);
        Mail::assertNotSent(DocumentStatusMail::class);
    }

    public function test_expired_session_does_not_change_already_approved_customer(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';
        $sessionId = 'd54203d5-1dee-44c8-bbdb-ebe1fc1418ef';
        $webhookSessionId = 'fdd2191b-ed54-411b-90f7-356265ff865f';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => DocumentStatus::APPROVED->value,
        ]);

        $session = CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => $sessionId,
            'status' => 'Approved',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-approved',
            'metadata' => [
                'application_id' => $applicationId,
                'session_id' => $webhookSessionId,
            ],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditStatusUpdatedPayload($applicationId, $webhookSessionId, 'Expired'),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame(DocumentStatus::APPROVED->value, $customer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::ACTIVE, $customer->status);
        $this->assertSame('Expired', $session->status);
        Mail::assertNotSent(DocumentStatusMail::class);
    }

    public function test_user_data_updated_does_not_apply_to_wrong_customer_when_application_id_is_shared(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';

        $wrongCustomer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);
        $targetCustomer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        $wrongSession = CustomerDiditSession::query()->create([
            'customer_id' => $wrongCustomer->id,
            'session_id' => '518423db-5519-41c0-97bc-25822727b1e4',
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-wrong',
            'metadata' => ['application_id' => $applicationId],
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $targetCustomer->id,
            'session_id' => '86281871-a1fd-48ec-aa43-444f6519c223',
            'status' => 'In Review',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-target-user',
            'metadata' => ['application_id' => $applicationId],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, 'vendor-target', 'Declined', [
                'vendor_data' => DiditVendorData::forCustomer($targetCustomer->id),
            ]),
        );

        $wrongCustomer->refresh();
        $targetCustomer->refresh();
        $wrongSession->refresh();

        $this->assertSame(DocumentStatus::ANALYSING->value, $wrongCustomer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $wrongCustomer->status);
        $this->assertSame('In Progress', $wrongSession->status);
        $this->assertSame(DocumentStatus::DECLINED->value, $targetCustomer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_REJECTED, $targetCustomer->status);
    }

    public function test_user_data_updated_without_unique_ids_does_not_use_application_id(): void
    {
        $applicationId = 'c159a625-9dde-444c-990d-9accf2298448';

        $wrongCustomer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        $session = CustomerDiditSession::query()->create([
            'customer_id' => $wrongCustomer->id,
            'session_id' => '518423db-5519-41c0-97bc-25822727b1e4',
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session-shared-app',
            'metadata' => ['application_id' => $applicationId],
        ]);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($applicationId, 'unknown-vendor', 'Declined'),
        );

        $wrongCustomer->refresh();
        $session->refresh();

        $this->assertSame(DocumentStatus::ANALYSING->value, $wrongCustomer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $wrongCustomer->status);
        $this->assertSame('In Progress', $session->status);
    }

    public function test_user_data_updated_active_does_not_overwrite_session_kyc_status(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
            'doc_status' => DocumentStatus::ANALYSING->value,
        ]);

        $session = $this->createDiditSession($customer, 'sess-active-entity');
        $session->update(['status' => 'In Review']);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload('shared-app-id', 'vendor-active', 'ACTIVE', [
                'vendor_data' => DiditVendorData::forCustomer($customer->id),
            ]),
        );

        $customer->refresh();
        $session->refresh();

        $this->assertSame('In Review', $session->status);
        $this->assertSame(DocumentStatus::ANALYSING->value, $customer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW, $customer->status);
        $this->assertSame(DiditVendorData::forCustomer($customer->id), $session->metadata['vendor_data']);
        $this->assertSame('vendor-active', $session->metadata['vendor_user_id']);
        Mail::assertNotSent(DocumentStatusMail::class);
    }

    public function test_abandoned_session_does_not_send_document_status_email(): void
    {
        $sessionId = 'sess-abandoned-001';

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
            'doc_status' => DocumentStatus::PENDING->value,
        ]);
        $this->createDiditSession($customer, $sessionId);

        app(IdentityVerificationWebhookService::class)->handleDataUpdated(
            $this->diditUserDataUpdatedPayload($sessionId, 'vendor-abandoned', 'Abandoned', [
                'vendor_data' => DiditVendorData::forCustomer($customer->id),
            ]),
        );

        $customer->refresh();

        $this->assertSame(DocumentStatus::PENDING->value, $customer->doc_status);
        $this->assertSame(CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION, $customer->status);
        Mail::assertNotSent(DocumentStatusMail::class);
    }
}

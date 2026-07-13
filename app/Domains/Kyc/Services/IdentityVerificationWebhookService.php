<?php

namespace App\Domains\Kyc\Services;

use App\Domains\Customer\Contracts\CorporateDocumentRepositoryInterface;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\Contracts\RetailDocumentRepositoryInterface;
use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Jobs\SendDocumentStatusEmail;
use App\Domains\Kyc\Jobs\UploadDocumentMediaJob;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Settings\Services\DiditSettingsService;
use App\Models\Customer;
use App\Models\CustomerCorporateDocument;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa webhooks do provedor KYC (Didit) — documentos, lifecycle e encaminhamento Ebury pós-aprovação.
 */
class IdentityVerificationWebhookService
{
    public function __construct(
        protected IdentityVerificationSessionRepositoryInterface $sessionRepository,
        protected RetailDocumentRepositoryInterface $retailDocumentRepository,
        protected CorporateDocumentRepositoryInterface $corporateDocumentRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected PhoneVerificationService $phoneVerificationService,
        protected CustomerLifecycleService $lifecycleService,
        protected FinancialProvisioningService $financialProvisioning,
        protected DiditSettingsService $diditSettings,
    ) {}

    public function handleDataUpdated(array $payload): void
    {
        if (($payload['webhook_type'] ?? null) === 'user.data.updated') {
            $this->handleUserDataUpdated($payload);

            return;
        }

        $this->handleStatusUpdated($payload);
    }

    private function handleUserDataUpdated(array $payload): void
    {
        if (isset($payload['decision']) && is_array($payload['decision'])) {
            $this->handleStatusUpdated($payload);

            return;
        }

        $rawStatus = $payload['status'] ?? null;

        if ($rawStatus === null || $rawStatus === '') {
            Log::warning('Didit user.data.updated sem status', [
                'external_ids' => $this->collectExternalSessionIds($payload),
                'event_id' => $payload['event_id'] ?? null,
            ]);

            return;
        }

        DB::transaction(function () use ($payload, $rawStatus) {
            $diditSession = $this->resolveDiditSession($payload);

            if (! $diditSession) {
                Log::warning('Didit user.data.updated: sessão não encontrada', [
                    'external_ids' => $this->collectExternalSessionIds($payload),
                    'event_id' => $payload['event_id'] ?? null,
                ]);

                return;
            }

            $customer = $this->customerRepository->findById($diditSession->customer_id);

            if (! $customer) {
                Log::warning('Didit user.data.updated: customer não encontrado', [
                    'customer_id' => $diditSession->customer_id,
                    'event_id' => $payload['event_id'] ?? null,
                ]);

                return;
            }

            $diditSession->update(['status' => $rawStatus]);
            $this->applyIdentityDecision($customer, [
                'status' => $this->normalizeDiditStatus((string) $rawStatus),
            ], $diditSession);

            Log::channel('diditDocumentJob')->info('Didit user.data.updated processado', [
                'customer_id' => $customer->id,
                'session_id' => $diditSession->session_id,
                'status' => $rawStatus,
            ]);
        });
    }

    private function handleStatusUpdated(array $payload): void
    {
        $decision = $payload['decision'] ?? [];
        $idVerificationsArray = $decision['id_verifications'] ?? [];

        DB::transaction(function () use ($payload, $decision, $idVerificationsArray) {
            $diditSession = $this->resolveDiditSession($payload);

            if (! $diditSession) {
                Log::warning('Didit status.updated: sessão não encontrada', [
                    'external_ids' => $this->collectExternalSessionIds($payload),
                    'event_id' => $payload['event_id'] ?? null,
                ]);

                return;
            }

            $customer = $this->customerRepository->findById($diditSession->customer_id);

            if (! $customer) {
                Log::warning('Didit status.updated: customer não encontrado', [
                    'customer_id' => $diditSession->customer_id,
                    'event_id' => $payload['event_id'] ?? null,
                ]);

                return;
            }

            $sessionStatus = $this->resolveRawStatus($payload, $decision);

            $this->enrichSessionMetadata($diditSession, $payload);

            if ($sessionStatus !== null) {
                $diditSession->update(['status' => $sessionStatus]);
            }

            if ($idVerificationsArray === []) {
                $this->applyIdentityDecision(
                    $customer,
                    $this->buildDecisionPayload($payload, $decision),
                    $diditSession,
                );

                return;
            }

            foreach ($idVerificationsArray as $idVerifications) {
                $this->processIdVerification(
                    $customer,
                    $diditSession,
                    $idVerifications,
                    $decision,
                    $payload,
                );
            }
        });
    }

    /** @param array<string, mixed> $idVerifications */
    private function processIdVerification(
        Customer $customer,
        CustomerDiditSession $diditSession,
        array $idVerifications,
        array $decision,
        array $payload,
    ): void {
        if ($this->isKyBSession($customer, $diditSession)) {
            $this->applyIdentityDecision(
                $customer,
                $this->buildDecisionPayload($payload, $decision, $idVerifications),
                $diditSession,
            );

            return;
        }

        $documentNumber = $this->resolveDocumentNumber($idVerifications, $diditSession);

        $customerDocument = $this->retailDocumentRepository->firstOrCreateByDocumentNumber(
            $documentNumber,
            $diditSession->customer_id,
        );

        $fillable = (new CustomerRetailDocument)->getFillable();
        $data = array_merge($idVerifications, $decision, $payload);
        $filteredData = array_intersect_key($data, array_flip($fillable));

        $mediaFields = [
            'front_image', 'back_image', 'full_front_image', 'full_back_image',
            'portrait_image', 'front_video', 'back_video',
            'back_image_camera_front', 'front_image_camera_front',
            'back_image_camera_front_face_match_score', 'front_image_camera_front_face_match_score',
        ];

        foreach ($filteredData as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $customerDocument->{$field} = $value;

            if (in_array($field, $mediaFields, true) && $this->shouldDispatchMediaUpload((string) $value)) {
                UploadDocumentMediaJob::dispatch($customerDocument->id, $field, $value);
            }
        }

        $this->persistIdVerificationExtras($customerDocument, $idVerifications);

        if ($customerDocument->isDirty()) {
            try {
                $this->retailDocumentRepository->save($customerDocument);
                Log::channel('diditDocumentJob')->info('Documento processado com sucesso', [
                    'customer_id' => $customerDocument->customer_id,
                    'session_id' => $diditSession->session_id,
                    'document_number' => $customerDocument->document_number,
                    'fields_updated' => array_keys($customerDocument->getChanges()),
                ]);
            } catch (\Throwable $e) {
                Log::channel('diditDocumentJob')->error('Erro salvando documento', [
                    'customer_id' => $customerDocument->customer_id ?? null,
                    'session_id' => $diditSession->session_id,
                    'document_number' => $documentNumber,
                    'exception' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->syncCustomerProfileFromDocument($customer, $idVerifications);

        $decisionPayload = $this->buildDecisionPayload($payload, $decision, $idVerifications);

        $this->applyIdentityDecision($customer, $decisionPayload, $diditSession);
    }

    /** @param array<string, mixed> $payload */
    private function resolveDiditSession(array $payload): ?CustomerDiditSession
    {
        $decision = $payload['decision'] ?? [];

        foreach ($this->collectDiditSessionIds($payload, $decision) as $sessionId) {
            $session = $this->sessionRepository->findBySessionId($sessionId);

            if ($session) {
                return $session;
            }
        }

        $applicationId = $payload['application_id'] ?? null;

        if (is_string($applicationId) && $applicationId !== '') {
            return $this->sessionRepository->findByApplicationId($applicationId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $decision
     * @return list<string>
     */
    private function collectDiditSessionIds(array $payload, array $decision): array
    {
        return array_values(array_unique(array_filter([
            $payload['session_id'] ?? null,
            $decision['session_id'] ?? null,
        ])));
    }

    /** @return list<string> */
    private function collectExternalSessionIds(array $payload): array
    {
        $decision = $payload['decision'] ?? [];

        return array_values(array_unique(array_filter([
            ...$this->collectDiditSessionIds($payload, $decision),
            $payload['application_id'] ?? null,
        ])));
    }

    /** @param array<string, mixed> $idVerifications */
    private function resolveDocumentNumber(array $idVerifications, CustomerDiditSession $diditSession): string
    {
        $documentNumber = $idVerifications['document_number'] ?? $idVerifications['personal_number'] ?? null;

        if (is_string($documentNumber) && $documentNumber !== '') {
            return $documentNumber;
        }

        return 'didit:'.$diditSession->session_id;
    }

    /** @param array<string, mixed> $decision */
    /** @param array<string, mixed>|null $idVerifications */
    private function buildDecisionPayload(array $payload, array $decision, ?array $idVerifications = null): array
    {
        $rawStatus = $idVerifications['status']
            ?? $decision['status']
            ?? $payload['status']
            ?? null;

        return [
            'status' => $rawStatus !== null ? $this->normalizeDiditStatus((string) $rawStatus) : null,
        ];
    }

    /** @param array<string, mixed> $decision */
    private function resolveRawStatus(array $payload, array $decision): ?string
    {
        $rawStatus = $decision['status'] ?? $payload['status'] ?? null;

        return $rawStatus !== null ? (string) $rawStatus : null;
    }

    /** @param array<string, mixed> $payload */
    private function enrichSessionMetadata(CustomerDiditSession $diditSession, array $payload): void
    {
        $metadata = is_array($diditSession->metadata) ? $diditSession->metadata : [];
        $decision = $payload['decision'] ?? [];
        $updated = false;

        foreach ([
            'application_id' => $payload['application_id'] ?? null,
            'session_id' => $payload['session_id'] ?? $decision['session_id'] ?? null,
        ] as $key => $value) {
            if (! is_string($value) || $value === '' || ($metadata[$key] ?? null) === $value) {
                continue;
            }

            $metadata[$key] = $value;
            $updated = true;
        }

        if ($updated) {
            $diditSession->metadata = $metadata;
            $diditSession->save();
        }
    }

    /** @param array<string, mixed> $idVerifications */
    private function persistIdVerificationExtras(CustomerRetailDocument $document, array $idVerifications): void
    {
        $extra = is_array($document->extra_fields) ? $document->extra_fields : [];

        foreach (['warnings', 'document_subtype', 'node_id', 'matches', 'mrz', 'status'] as $key) {
            if (! array_key_exists($key, $idVerifications) || $idVerifications[$key] === null) {
                continue;
            }

            $extra[$key] = $idVerifications[$key];
        }

        if ($extra === ($document->extra_fields ?? [])) {
            return;
        }

        $document->extra_fields = $extra;
    }

    /** @param array<string, mixed> $idVerifications */
    private function syncCustomerProfileFromDocument(Customer $customer, array $idVerifications): void
    {
        $name = $this->resolveCustomerNameFromIdVerification($idVerifications);

        if ($name === null) {
            return;
        }

        $customer->name = $name;
        $this->customerRepository->save($customer);
    }

    /** @param array<string, mixed> $idVerifications */
    private function resolveCustomerNameFromIdVerification(array $idVerifications): ?string
    {
        $fullName = trim((string) ($idVerifications['full_name'] ?? ''));

        if ($fullName !== '') {
            return $fullName;
        }

        $firstName = trim((string) ($idVerifications['first_name'] ?? ''));
        $lastName = trim((string) ($idVerifications['last_name'] ?? ''));
        $combined = trim($firstName.' '.$lastName);

        return $combined !== '' ? $combined : null;
    }

    private function normalizeDiditStatus(string $status): string
    {
        $normalized = strtolower(str_replace([' ', '_'], '', $status));

        return match ($normalized) {
            'approved' => 'Approved',
            'declined' => 'Declined',
            'inreview' => 'In Review',
            'inprogress', 'notfinished' => 'In Progress',
            'notstarted' => 'Not Started',
            default => $status,
        };
    }

    private function shouldDispatchMediaUpload(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function applyIdentityDecision(Customer $customer, array $decision, ?CustomerDiditSession $diditSession = null): void
    {
        if ($this->isKyBSession($customer, $diditSession)) {
            $this->applyKyBDecision($customer, $decision);

            return;
        }

        $status = $decision['status'] ?? null;

        if ($status === null) {
            return;
        }

        $statusForEmail = match ($status) {
            'Approved' => 'approved',
            'Declined' => 'declined',
            'In Review', 'In Progress' => 'pending',
            'Not Started' => 'pending',
            default => strtolower($status),
        };

        if ($statusForEmail) {
            SendDocumentStatusEmail::dispatch($customer, $statusForEmail)->onQueue('emails');
        }

        if (in_array($status, ['Approved', 'In Review', 'In Progress'], true)) {
            $this->phoneVerificationService->sendSms($customer, $customer->phone);
        }

        match ($status) {
            'Approved' => $customer->doc_status = DocumentStatus::APPROVED,
            'Declined' => $customer->doc_status = DocumentStatus::DECLINED,
            'In Review' => $customer->doc_status = DocumentStatus::ANALYSING,
            'In Progress' => $customer->doc_status = DocumentStatus::ANALYSING,
            'Not Started' => $customer->doc_status = DocumentStatus::PENDING,
            default => null,
        };

        match ($customer->doc_status) {
            DocumentStatus::APPROVED => $this->lifecycleService->transitionTo(
                $customer,
                CustomerLifecycleStatus::PROVISIONING,
                'Documentos KYC aprovados. Aprovisionando Ebury e Wallester.',
            ),
            DocumentStatus::PENDING => $this->lifecycleService->transitionTo($customer, CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION),
            DocumentStatus::DECLINED => $this->lifecycleService->transitionTo(
                $customer,
                CustomerLifecycleStatus::IDENTITY_REJECTED,
                'Identidade recusada pelo provedor de verificação.',
            ),
            DocumentStatus::ANALYSING => $this->lifecycleService->transitionTo($customer, CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW),
            default => null,
        };

        if ($customer->doc_status === DocumentStatus::APPROVED) {
            $this->financialProvisioning->dispatchPostKycPartners($customer);
        }

        $this->customerRepository->save($customer);
    }

    private function isKyBSession(Customer $customer, ?CustomerDiditSession $diditSession): bool
    {
        if ($customer->type === 'corporate') {
            return true;
        }

        return $diditSession !== null
            && $this->diditSettings->isKybWorkflow($diditSession->workflow_id);
    }

    /** @param array<string, mixed> $decision */
    private function applyKyBDecision(Customer $customer, array $decision): void
    {
        $status = $decision['status'] ?? null;

        if ($status === null) {
            return;
        }

        $statusForEmail = match ($status) {
            'Approved' => 'approved',
            'Declined' => 'declined',
            'In Review', 'In Progress' => 'pending',
            'Not Started' => 'pending',
            default => strtolower($status),
        };

        if ($statusForEmail) {
            SendDocumentStatusEmail::dispatch($customer, $statusForEmail)->onQueue('emails');
        }

        $corporateDocument = $this->corporateDocumentRepository->latestByCustomer($customer);

        if ($corporateDocument === null) {
            $corporateDocument = CustomerCorporateDocument::query()->create([
                'customer_id' => $customer->id,
                'legal_name' => $customer->name ?: $customer->username,
                'country_code' => 'PT',
                'tax_id' => 'kyb_'.$customer->id.'_'.uniqid(),
                'document_type' => 'BUSINESS_ID',
                'doc_status' => DocumentStatus::ANALYSING->value,
                'status' => 'Pending',
            ]);
        }

        match ($status) {
            'Approved' => $this->applyKyBApproved($customer, $corporateDocument),
            'Declined' => $this->applyKyBDeclined($customer, $corporateDocument, $decision),
            'In Review', 'In Progress' => $this->applyKyBAnalysing($customer, $corporateDocument),
            'Not Started' => $this->applyKyBPending($customer, $corporateDocument),
            default => null,
        };

        $corporateDocument->save();
        $this->customerRepository->save($customer);
    }

    private function applyKyBApproved(Customer $customer, CustomerCorporateDocument $corporateDocument): void
    {
        $corporateDocument->status = 'Valid';
        $corporateDocument->doc_status = DocumentStatus::APPROVED->value;
        $customer->doc_status = DocumentStatus::APPROVED->value;
    }

    /** @param array<string, mixed> $decision */
    private function applyKyBDeclined(Customer $customer, CustomerCorporateDocument $corporateDocument, array $decision): void
    {
        $corporateDocument->status = 'Inactive';
        $corporateDocument->doc_status = DocumentStatus::DECLINED->value;
        $corporateDocument->motive = $decision['motive'] ?? 'KYB verification declined.';
        $customer->doc_status = DocumentStatus::DECLINED->value;
    }

    private function applyKyBAnalysing(Customer $customer, CustomerCorporateDocument $corporateDocument): void
    {
        $corporateDocument->status = 'Pending';
        $corporateDocument->doc_status = DocumentStatus::ANALYSING->value;
        $customer->doc_status = DocumentStatus::ANALYSING->value;
    }

    private function applyKyBPending(Customer $customer, CustomerCorporateDocument $corporateDocument): void
    {
        $corporateDocument->status = 'Pending';
        $corporateDocument->doc_status = DocumentStatus::PENDING->value;
        $customer->doc_status = DocumentStatus::PENDING->value;
    }
}

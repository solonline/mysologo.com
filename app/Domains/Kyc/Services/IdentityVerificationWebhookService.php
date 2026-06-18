<?php

namespace App\Domains\Kyc\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\Contracts\RetailDocumentRepositoryInterface;
use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Jobs\SendDocumentStatusEmail;
use App\Domains\Kyc\Jobs\UploadDocumentMediaJob;
use App\Models\Customer;
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
        protected CustomerRepositoryInterface $customerRepository,
        protected PhoneVerificationService $phoneVerificationService,
        protected CustomerLifecycleService $lifecycleService,
        protected FinancialProvisioningService $financialProvisioning,
    ) {}

    public function handleDataUpdated(array $payload): void
    {
        $sessionId = $payload['session_id'] ?? null;
        $decision = $payload['decision'] ?? [];
        $idVerificationsArray = $decision['id_verifications'] ?? [];

        DB::transaction(function () use ($payload, $decision, $idVerificationsArray, $sessionId) {
            foreach ($idVerificationsArray as $idVerifications) {
                $documentNumber = $idVerifications['document_number'] ?? null;

                if (! $documentNumber) {
                    Log::warning('Documento sem número ignorado', ['session_id' => $sessionId]);
                    continue;
                }

                $diditSession = $this->sessionRepository->findBySessionId($sessionId);

                if (! $diditSession) {
                    Log::warning('Sessão de verificação não encontrada', ['session_id' => $sessionId]);
                    continue;
                }

                $customerDocument = $this->retailDocumentRepository->firstOrCreateByDocumentNumber(
                    $documentNumber,
                    $diditSession->customer_id
                );

                $fillable = (new CustomerRetailDocument())->getFillable();
                $data = array_merge($idVerifications, $decision, $payload);
                $filteredData = array_intersect_key($data, array_flip($fillable));

                $mediaFields = [
                    'front_image', 'back_image', 'full_front_image', 'full_back_image',
                    'portrait_image', 'front_video', 'back_video',
                    'back_image_camera_front', 'front_image_camera_front',
                    'back_image_camera_front_face_match_score', 'front_image_camera_front_face_match_score',
                ];

                foreach ($filteredData as $field => $value) {
                    $customerDocument->{$field} = $value;

                    if (in_array($field, $mediaFields, true) && ! empty($value)) {
                        UploadDocumentMediaJob::dispatch($customerDocument->id, $field, $value);
                    }
                }

                $customer = $this->customerRepository->findById($customerDocument->customer_id);
                $this->lifecycleService->transitionTo($customer, CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW);

                if ($customerDocument->isDirty()) {
                    try {
                        $this->retailDocumentRepository->save($customerDocument);
                        Log::channel('diditDocumentJob')->info('Documento processado com sucesso', [
                            'customer_id' => $customerDocument->customer_id,
                            'session_id' => $sessionId,
                            'document_number' => $customerDocument->document_number,
                            'fields_updated' => array_keys($customerDocument->getChanges()),
                        ]);
                    } catch (\Throwable $e) {
                        Log::channel('diditDocumentJob')->error('Erro salvando documento', [
                            'customer_id' => $customerDocument->customer_id ?? null,
                            'session_id' => $sessionId,
                            'document_number' => $documentNumber,
                            'exception' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }

                $this->applyIdentityDecision($customer, $decision);
            }
        });
    }

    private function applyIdentityDecision(Customer $customer, array $decision): void
    {
        $statusForEmail = match ($decision['status'] ?? null) {
            'Approved' => 'approved',
            'Declined' => 'declined',
            'In Review', 'In Progress' => 'pending',
            'Not Started' => 'pending',
            default => strtolower($decision['status'] ?? 'pending'),
        };

        if ($statusForEmail) {
            SendDocumentStatusEmail::dispatch($customer, $statusForEmail)->onQueue('emails');
        }

        if (in_array($decision['status'] ?? null, ['Approved', 'In Review', 'In Progress'], true)) {
            $this->phoneVerificationService->sendSms($customer, $customer->phone);
        }

        match ($decision['status'] ?? null) {
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
            $this->financialProvisioning->provisionPostKycPartners($customer);
            $this->lifecycleService->evaluateActivation($customer->fresh());
        }

        $this->customerRepository->save($customer);
    }
}

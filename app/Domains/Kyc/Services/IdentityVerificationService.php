<?php

namespace App\Domains\Kyc\Services;

use App\Domains\Customer\Contracts\CorporateDocumentRepositoryInterface;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\Services\CorporateDocumentService;
use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Domains\Kyc\Contracts\KycGatewayInterface;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Settings\Services\DiditSettingsService;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;

/**
 * Orquestra verificação de identidade (KYC) — agnóstico ao provedor via KycGatewayInterface.
 */
class IdentityVerificationService
{
    public function __construct(
        protected KycGatewayInterface $gateway,
        protected IdentityVerificationSessionRepositoryInterface $sessionRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected CorporateDocumentRepositoryInterface $corporateDocumentRepository,
        protected CorporateDocumentService $corporateDocumentService,
        protected DiditSettingsService $diditSettings,
    ) {}

    public function createSession(Customer $customer, ?string $workflowId = null): array
    {
        $workflowId = $workflowId ?? $this->resolveWorkflowId($customer);

        if ($customer->type === 'corporate') {
            $this->corporateDocumentService->initiateKyBReview($customer);
        }

        $sessionData = [
            'workflow_id' => $workflowId,
            'customer_id' => $customer->id,
            'callback' => config('app.url_frontend').'/verification-pending',
            'callback_method' => 'initiator',
            'language' => $customer->language ?? 'en',
        ];

        $result = $this->gateway->createSession($sessionData);

        if ($result['status'] === 'success' && isset($result['session']['session_id'])) {
            $body = $result['session'];
            $diditSession = $this->sessionRepository->upsertBySessionId(
                $customer,
                $body['session_id'],
                [
                    'workflow_id' => $body['workflow_id'] ?? $workflowId,
                    'session_url' => $body['url'] ?? null,
                    'status' => $body['status'] ?? 'pending',
                    'callback' => $sessionData['callback'],
                    'metadata' => $this->buildSessionMetadata($body),
                ]
            );

            return [
                'status' => 'success',
                'message' => null,
                'session' => $diditSession->toArray(),
            ];
        }

        return [
            'status' => 'failed',
            'message' => $result['message'] ?? 'Erro ao criar sessão de verificação',
            'session' => null,
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->gateway->getSessionStatus($sessionId);
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return $this->gateway->verifyWebhookSignature($payload, $signature);
    }

    public function ensureValidIndividualDocument(Customer $customer): array
    {
        return $this->ensureActiveSession($customer, $this->diditSettings->workflowId());
    }

    public function ensureValidCorporateKyB(Customer $customer): array
    {
        if ($customer->type !== 'corporate') {
            throw new ApiException(
                ErrorCode::CORPORATE_DOCUMENT_INVALID,
                400,
                'KYB verification is only available for corporate customers.',
            );
        }

        $responsible = $this->customerRepository->firstResponsible($customer);

        if ($responsible === null) {
            throw new ApiException(
                ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                403,
                'No responsible person linked to this company.',
            );
        }

        if (! $this->isKycApproved($responsible)) {
            return [
                'status' => 'failed',
                'message' => 'Legal representative KYC must be approved before company verification.',
                'session' => null,
            ];
        }

        return $this->ensureActiveSession($customer, $this->diditSettings->workflowKybId());
    }

    public function ensureValidCorporateAndResponsibleDocuments(Customer $customer): void
    {
        if ($customer->corporate_id) {
            $this->ensureValidIndividualDocument($customer);
        } else {
            $responsible = $this->customerRepository->firstResponsible($customer);

            if ($responsible) {
                $this->ensureValidIndividualDocument($responsible);
            } else {
                throw new ApiException(
                    ErrorCode::CORPORATE_WITHOUT_RESPONSIBLE,
                    403,
                    'No responsible person linked to this company.'
                );
            }
        }

        $corporateDocument = $this->corporateDocumentRepository->latestByCustomer($customer);

        if (! $corporateDocument) {
            throw new ApiException(
                ErrorCode::CORPORATE_DOCUMENT_MISSING,
                403,
                'No company verification found. Please complete KYB verification to access the account.'
            );
        }

        if ($corporateDocument->status !== 'Valid') {
            $reason = $corporateDocument->motive ? (': '.$corporateDocument->motive) : '';

            throw new ApiException(
                ErrorCode::CORPORATE_DOCUMENT_INVALID,
                403,
                'The company document is not valid. Current status: '.$corporateDocument->status.$reason.'. Please resolve the issue to access the account.'
            );
        }
    }

    private function ensureActiveSession(Customer $customer, string $workflowId): array
    {
        $session = $this->sessionRepository->latestByCustomer($customer);

        if (! isset($session) || in_array($session->status, ['Approved', 'Not Started'])) {
            if (! isset($session) || $session->updated_at->addDays(7)->isPast()) {
                return $this->createSession($customer, $workflowId);
            }

            $action = match ($session->status) {
                'Declined', 'Abandoned', 'Expired' => fn () => $this->createSession($customer, $workflowId),
                default => null,
            };

            if ($action) {
                return $action();
            }
        }

        return [
            'status' => 'success',
            'message' => null,
            'session' => $session?->toArray(),
        ];
    }

    private function resolveWorkflowId(Customer $customer): string
    {
        return match ($customer->type) {
            'corporate' => $this->diditSettings->workflowKybId(),
            default => $this->diditSettings->workflowId(),
        };
    }

    private function isKycApproved(Customer $customer): bool
    {
        $status = strtolower((string) $customer->doc_status);

        return in_array($status, [strtolower(DocumentStatus::APPROVED->value), 'approved'], true);
    }

    /** @param array<string, mixed> $body */
    private function buildSessionMetadata(array $body): ?array
    {
        $metadata = is_array($body['metadata'] ?? null) ? $body['metadata'] : [];

        if (isset($body['application_id'])) {
            $metadata['application_id'] = $body['application_id'];
        }

        if (isset($body['session_id'])) {
            $metadata['session_id'] = $body['session_id'];
        }

        return $metadata === [] ? null : $metadata;
    }
}

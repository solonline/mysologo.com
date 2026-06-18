<?php

namespace App\Domains\Kyc\Services;

use App\Domains\Customer\Contracts\CorporateDocumentRepositoryInterface;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Kyc\Contracts\IdentityVerificationSessionRepositoryInterface;
use App\Domains\Kyc\Contracts\KycGatewayInterface;
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
    ) {}

    public function createSession(Customer $customer): array
    {
        $sessionData = [
            'workflow_id' => config('services.didit.workflow_id'),
            'customer_id' => $customer->id,
            'callback' => config('app.url_frontend') . '/verification-pending',
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
                    'workflow_id' => $body['workflow_id'] ?? null,
                    'session_url' => $body['url'] ?? null,
                    'status' => $body['status'] ?? 'pending',
                    'callback' => $sessionData['callback'],
                    'metadata' => $body['metadata'] ?? null,
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
        $session = $this->sessionRepository->latestByCustomer($customer);

        if (! isset($session) || in_array($session->status, ['Approved', 'Not Started'])) {
            if (! isset($session) || $session->updated_at->addDays(7)->isPast()) {
                return $this->createSession($customer);
            }

            $action = match ($session->status) {
                'Declined', 'Abandoned', 'Expired' => fn () => $this->createSession($customer),
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
                'No one document found for this company. Please submit the required documents to access the account.'
            );
        }

        if ($corporateDocument->status !== 'Valid') {
            $reason = $corporateDocument->motive ? (': ' . $corporateDocument->motive) : '';

            throw new ApiException(
                ErrorCode::CORPORATE_DOCUMENT_INVALID,
                403,
                'The company document is not valid. Current status: ' . $corporateDocument->status . $reason . '. Please resolve the issue to access the account.'
            );
        }
    }
}

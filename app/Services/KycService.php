<?php
namespace App\Services;

use App\Contracts\CorporateDocumentRepositoryInterface;
use App\Contracts\CustomerRepositoryInterface;
use App\Contracts\DiditSessionRepositoryInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;

class KycService
{
    public function __construct(
        protected DiditService $diditService,
        protected DiditSessionRepositoryInterface $diditSessionRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected CorporateDocumentRepositoryInterface $corporateDocumentRepository,
    ) {}

    public function ensureValidIndividualDocument(Customer $customer): array
    {
        $customerDiditSession = $this->diditSessionRepository->latestByCustomer($customer);

        if (!isset($customerDiditSession) || in_array($customerDiditSession->status, ['Approved', 'Not Started'])) {
            if (!isset($customerDiditSession) || $customerDiditSession->updated_at->addDays(7)->isPast()) {
                return $this->diditService->createSession($customer);
            } else {
                $action = match ($customerDiditSession->status) {
                    'Declined', 'Abandoned', 'Expired' => fn() => $this->diditService->createSession($customer),
                    default => null,
                };
                if ($action) {
                    return $action();
                }
            }
        }

        return [
            'status'  => 'success',
            'message' => null,
            'session' => $customerDiditSession?->toArray(),
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
        if (!$corporateDocument) {
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


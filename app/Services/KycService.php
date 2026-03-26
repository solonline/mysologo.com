<?php
namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\User;

class KycService
{
    public function __construct(protected DiditService $diditService)
    {

    }
    public function ensureValidIndividualDocument(Customer $customer): array
    {
        $customerDiditSession = $customer->diditSessions()->latest()->first();

        // Se não tem sessão ou está aprovada/não iniciada, tenta criar nova sessão se necessário
        if (!isset($customerDiditSession) ||  in_array($customerDiditSession->status, ['Approved', 'Not Started'])) {
            $diditService = $this->diditService;
            if (!isset($customerDiditSession) || $customerDiditSession->updated_at->addDays(7)->isPast()) {
                return $diditService->createSession($customer);
            } else {
                $action = match ($customerDiditSession->status) {
                    'Declined', 'Abandoned', 'Expired' => function () use ($customer, $diditService) {
                            return $diditService->createSession($customer);
                        },
                    default => null,
                };
                if ($action)
                    return $action();
            }
        }
        // Se já tem sessão válida, retorna status success e sessão existente
        return [
            'status' => 'success',
            'message' => null,
            'session' => $customerDiditSession ? $customerDiditSession->toArray() : null,
        ];
    }

    public function ensureValidCorporateAndResponsibleDocuments(Customer $customer): void
    {
            // O responsável é o customer logado (caso corporate_id != null)
            if ($customer->corporate_id) {
                // O customer logado é o responsável, então validamos o documento de pessoa física
                $this->ensureValidIndividualDocument($customer);
            } else {
                // Se for login da empresa (sem company_id), validar o responsável principal (assumindo apenas 1)
                $responsible = $customer->responsibles()->first();
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
            // 2. Validação do documento da empresa
            $corporateDocument = $customer->corporateDocuments()->latest()->first();
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

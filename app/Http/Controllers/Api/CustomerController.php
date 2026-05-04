<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateResponsibleDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Http\Requests\CreateResponsibleRequest;
use App\Http\Requests\SubmitCorporateDocumentRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService)
    {
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $requiresResponsible = false;
        $kycStatus = false;
        $diditSession = null;
        $message = null;

        if ($user->type === 'corporate') {
            $responsible = $user->responsibles()->first();
            if (is_null($responsible)) {
                $requiresResponsible = true;
                $kycStatus = false;
            } else {
                $diditSession = $responsible->diditSessions()->latest()->first();
                $kycStatus = $user->doc_status;
                if (!$diditSession) {
                    $message = 'No Didit session found for responsible. Please complete KYC.';
                }
                $diditSession = $diditSession ? $diditSession->toArray() : null;
            }
        } elseif ($user->type === 'retail') {
            $kycService = app(\App\Services\KycService::class);
            $kyc = $kycService->ensureValidIndividualDocument($user);
            $diditSession = $kyc['session'] ?? null;
            $kycStatus = $user->doc_status;
            $message = $kyc['message'] ?? null;
        }

        $userArray = $user->toArray();
        $response = array_merge($userArray, [
            'requires_responsible' => $requiresResponsible,
            'kyc_status' => $kycStatus,
            'email_verified' => $user->hasVerifiedEmail(),
            'document_verified' => $user->doc_status,
            'didit_session' => $diditSession,
            'message' => $message,
        ]);
        return response()->json($response);
    }

    public function update(UpdateCustomerRequest $request)
    {
        $dto = UpdateCustomerDTO::fromArray($request->validated());
        return $this->customerService->update($request->user(), $dto);
    }

    public function createResponsible(CreateResponsibleRequest $request)
    {
        $dto = CreateResponsibleDTO::fromArray($request->validated());
        return $this->customerService->updateOrCreateResponsible($request->user(), $dto);
    }

    // public function submitCorporateDocuments(SubmitCorporateDocumentRequest $request)
    public function submitCorporateDocuments(SubmitCorporateDocumentRequest $request)
    {
        $result = $this->customerService->uploadDocument(
            $request->user(),
            $request->file('document'),      // UploadedFile
            'front_document',                // field
            $request->input('country')     // country
        );
        return response()->json($result, 201);
    }

    public function sendValidationEmail(Request $request)
    {
        $customer = $request->user();
        $validationUrl = $this->customerService->generateEmailValidationUrl($customer);
        $this->customerService->sendValidationEmail($customer, $validationUrl);
        return response()->json(['message' => 'Validation email sent.']);
    }
}

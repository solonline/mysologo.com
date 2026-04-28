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
        return response()->json($request->user());
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

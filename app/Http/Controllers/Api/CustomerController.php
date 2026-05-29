<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateResponsibleDTO;
use App\DTOs\UpdateCustomerDTO;
use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Http\Requests\CreateResponsibleRequest;
use App\Http\Requests\SubmitCorporateDocumentRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\FindByUsernameRequest;
use App\Http\Requests\Customer\UploadAvatarRequest;
use App\Http\Resources\CustomerProfileResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DocumentUploadResource;
use App\Http\Resources\PublicCustomerResource;
use App\Http\Resources\ResponsibleResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService)
    {
    }

    /**
     * Get Authenticated Customer
     *
     * Returns the profile of the authenticated customer along with KYC and verification status.
     *
     * @tags Profile
     */
    public function me(Request $request): CustomerProfileResource
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

        return CustomerProfileResource::make([
            'customer'             => $user,
            'requires_responsible' => $requiresResponsible,
            'kyc_status'           => $kycStatus,
            'email_verified'       => $user->hasVerifiedEmail(),
            'document_verified'    => $user->doc_status,
            'didit_session'        => $diditSession,
            'phone_verified'       => $user->phoneVerifications()->where('verified_at', '!=', null)->exists(),
            'message'              => $message,
        ]);
    }

    /**
     * Update Profile
     *
     * Updates one or more fields of the authenticated customer's profile. All fields are optional.
     *
     * @tags Profile
     */
    public function update(UpdateCustomerRequest $request): CustomerResource
    {
        $dto = UpdateCustomerDTO::fromArray($request->validated());
        $customer = $this->customerService->update($request->user(), $dto);
        return CustomerResource::make($customer);
    }

    /**
     * Create Corporate Responsible
     *
     * Creates or updates the legal representative linked to a corporate account. Required before KYC verification can proceed.
     *
     * @tags KYC
     */
    public function createResponsible(CreateResponsibleRequest $request): ResponsibleResource
    {
        $dto = CreateResponsibleDTO::fromArray($request->validated());
        $result = $this->customerService->updateOrCreateResponsible($request->user(), $dto);
        return ResponsibleResource::make($result);
    }

    /**
     * Submit Corporate Document
     *
     * Uploads the company's identification document (PDF) for KYC review.
     *
     * @tags KYC
     */
    public function submitCorporateDocuments(SubmitCorporateDocumentRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $result = $this->customerService->uploadDocument(
            $request->user(),
            $request->file('document'),
            'front_document',
            $request->input('country')
        );
        return DocumentUploadResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Upload Avatar
     *
     * Replaces the customer's profile picture.
     *
     * @tags Profile
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $customer = $this->customerService->uploadAvatar(
            $request->user(),
            $request->file('avatar')
        );

        return response()->json([
            'avatar'     => $customer->avatar,
            'avatar_url' => asset('storage/' . $customer->avatar),
        ]);
    }

    /**
     * Find Customer by Username
     *
     * Looks up an active, KYC-approved customer by username. Used for P2P transfer recipient lookup. The authenticated customer is always excluded from results.
     *
     * @tags Profile
     */
    public function findByUsername(FindByUsernameRequest $request): JsonResponse
    {
        $customer = $this->customerService->findByUsername(
            username:  $request->route('username'),
            filters:   [
                'status'     => CustomerStatus::ACTIVE,
                'doc_status' => DocumentStatus::APPROVED,
            ],
            excludeId: $request->user()->id,
        );

        return response()->json(new PublicCustomerResource($customer));
    }

    /**
     * Send Validation Email
     *
     * Sends (or resends) the email address verification link to the authenticated customer.
     *
     * @tags Profile
     */
    public function sendValidationEmail(Request $request): JsonResponse
    {
        $customer = $request->user();
        $validationUrl = $this->customerService->generateEmailValidationUrl($customer);
        $this->customerService->sendValidationEmail($customer, $validationUrl);
        return response()->json(['message' => 'Validation email sent.']);
    }
}

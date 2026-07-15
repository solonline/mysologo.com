<?php

namespace App\Domains\Customer\Controllers;

use App\Domains\Customer\DTOs\CreateResponsibleDTO;
use App\Domains\Customer\DTOs\UpdateCustomerDTO;
use App\Domains\Customer\Services\CustomerDocumentService;
use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Http\Requests\CreateResponsibleRequest;
use App\Http\Requests\Customer\FindByUsernameRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\UploadAvatarRequest;
use App\Http\Resources\CustomerProfileResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PublicCustomerResource;
use App\Http\Resources\ResponsibleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerProfileService $profile,
        protected CustomerDocumentService $documents,
        protected IdentityVerificationService $identityVerification,
        protected CustomerPhoneChangeService $phoneChange,
    ) {}

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

            if ($responsible === null) {
                $requiresResponsible = true;
                $kycStatus = $user->doc_status;
                $message = 'A legal representative must be registered before identity verification.';
            } elseif (! in_array(strtolower((string) $responsible->doc_status), ['approved', strtolower(DocumentStatus::APPROVED->value)], true)) {
                $kycStatus = $user->doc_status;
                $diditSession = $responsible->diditSessions()->latest()->first();
                $message = 'Legal representative must complete KYC before company verification.';
                $diditSession = $diditSession?->toArray();
            } else {
                $kyb = $this->identityVerification->ensureValidCorporateKyB($user);
                $diditSession = $kyb['session'] ?? null;
                $kycStatus = $user->doc_status;
                $message = $kyb['message'] ?? null;
            }
        } elseif ($user->type === 'retail') {
            $kyc = $this->identityVerification->ensureValidIndividualDocument($user);
            $diditSession = $kyc['session'] ?? null;
            $kycStatus = $user->doc_status;
            $message = $kyc['message'] ?? null;
        }

        return CustomerProfileResource::make([
            'customer' => $user,
            'requires_responsible' => $requiresResponsible,
            'kyc_status' => $kycStatus,
            'email_verified' => $user->hasVerifiedEmail(),
            'document_verified' => $user->doc_status,
            'didit_session' => $diditSession,
            'phone_verified' => $this->phoneChange->isCurrentPhoneVerified($user),
            'message' => $message,
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
        $customer = $this->profile->update($request->user(), $dto);

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
        $result = $this->profile->updateOrCreateResponsible($request->user(), $dto);

        return ResponsibleResource::make($result);
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
        $customer = $this->documents->uploadAvatar(
            $request->user(),
            $request->file('avatar'),
        );

        return response()->json([
            'avatar' => $customer->avatar,
            'avatar_url' => asset('storage/'.$customer->avatar),
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
        $customer = $this->profile->findByUsername(
            username: $request->route('username'),
            filters: [
                'status' => CustomerLifecycleStatus::ACTIVE,
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
        $validationUrl = $this->profile->generateEmailValidationUrl($customer);
        $this->profile->sendValidationEmail($customer, $validationUrl);

        return response()->json(['message' => 'Validation email sent.']);
    }
}

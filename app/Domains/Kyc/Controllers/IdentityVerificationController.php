<?php

namespace App\Domains\Kyc\Controllers;

use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\DiditSessionResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentityVerificationController extends Controller
{
    public function __construct(
        protected IdentityVerificationService $identityVerification,
    ) {}

    /**
     * Cria ou reutiliza sessão de verificação de identidade (Didit).
     *
     * @tags KYC
     */
    public function createSession(Request $request)
    {
        /** @var Customer $customer */
        $customer = Auth::user();

        $result = match ($customer->type) {
            'corporate' => $this->identityVerification->ensureValidCorporateKyB($customer),
            default => $this->identityVerification->ensureValidIndividualDocument($customer),
        };

        return DiditSessionResource::make($result);
    }
}

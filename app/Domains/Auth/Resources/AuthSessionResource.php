<?php

namespace App\Domains\Auth\Resources;

use App\Domains\Onboarding\DTOs\OnboardingStatus;
use App\Domains\Onboarding\Resources\IdentityVerificationSessionResource;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta de sessão autenticada (v2) — nomes genéricos, sem referência ao Didit.
 */
class AuthSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OnboardingStatus $onboarding */
        $onboarding = $this->resource['onboarding_status'];
        $status = $onboarding->toArray();
        $identityVerification = $onboarding->identityVerificationForAuth();

        return [
            'token' => $this->resource['token'],
            'user' => CustomerResource::make($this->resource['user']),
            'lifecycle' => $status['lifecycle'],
            'capabilities' => $status['capabilities'],
            'integrations' => $status['integrations'],
            'onboarding' => $status['onboarding'],
            'verification' => $status['verification'],
            'identity_verification' => $identityVerification === null ? null : [
                'status' => $identityVerification['status'],
                'session' => ($identityVerification['session'] ?? null)
                    ? IdentityVerificationSessionResource::make($identityVerification['session'])
                    : null,
            ],
        ];
    }
}

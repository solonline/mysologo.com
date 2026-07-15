<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\DTOs\OnboardingStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use Tests\TestCase;

class OnboardingStatusTest extends TestCase
{
    public function test_identity_verification_for_auth_is_null_when_kyc_approved(): void
    {
        $status = $this->makeStatus(
            identityStatus: DocumentStatus::APPROVED->value,
            identitySession: [
                'status' => 'Approved',
                'session_id' => 'sess_1',
                'session_url' => 'https://verify.didit.me/session/1',
            ],
            lifecycleStatus: CustomerLifecycleStatus::ACTIVE,
        );

        $this->assertNull($status->identityVerificationForAuth());
    }

    public function test_identity_verification_for_auth_omits_session_when_expired(): void
    {
        $status = $this->makeStatus(
            identityStatus: DocumentStatus::PENDING->value,
            identitySession: [
                'status' => 'Expired',
                'session_id' => 'sess_expired',
                'session_url' => 'https://verify.didit.me/session/expired',
            ],
        );

        $payload = $status->identityVerificationForAuth();

        $this->assertSame('Expired', $payload['status']);
        $this->assertNull($payload['session']);
    }

    public function test_identity_verification_for_auth_omits_session_when_in_review(): void
    {
        $status = $this->makeStatus(
            identityStatus: DocumentStatus::ANALYSING->value,
            identitySession: [
                'status' => 'In Review',
                'session_id' => 'sess_review',
                'session_url' => 'https://verify.didit.me/session/review',
            ],
            lifecycleStatus: CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW,
        );

        $payload = $status->identityVerificationForAuth();

        $this->assertSame('In Review', $payload['status']);
        $this->assertNull($payload['session']);
    }

    public function test_identity_verification_for_auth_includes_session_when_action_required(): void
    {
        $session = [
            'status' => 'In Progress',
            'session_id' => 'sess_progress',
            'session_url' => 'https://verify.didit.me/session/progress',
        ];

        $status = $this->makeStatus(
            identityStatus: DocumentStatus::PENDING->value,
            identitySession: $session,
            lifecycleStatus: CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
        );

        $payload = $status->identityVerificationForAuth();

        $this->assertSame('In Progress', $payload['status']);
        $this->assertSame($session, $payload['session']);
    }

    private function makeStatus(
        string $identityStatus,
        ?array $identitySession,
        CustomerLifecycleStatus $lifecycleStatus = CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
    ): OnboardingStatus {
        return new OnboardingStatus(
            emailVerified: true,
            phoneVerified: true,
            identityStatus: $identityStatus,
            identitySession: $identitySession,
            requiresResponsible: false,
            lifecycleStatus: $lifecycleStatus,
            statusReason: null,
            capabilities: [],
            integrations: [],
        );
    }
}

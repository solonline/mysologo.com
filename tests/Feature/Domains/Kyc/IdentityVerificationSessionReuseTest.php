<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\DiditVendorData;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\OnboardingStatusResolver;
use App\Models\Customer;
use App\Models\CustomerDiditSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class IdentityVerificationSessionReuseTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
    }

    public function test_expired_session_creates_a_new_didit_session(): void
    {
        $this->fakeDiditSessionCreate('sess_new_expired', 'https://didit.test/session-new-expired');

        $customer = $this->createPendingRetailCustomer();
        $this->seedSession($customer, 'sess_old_expired', 'Expired', 'https://didit.test/session-old-expired');

        $response = $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/kyc/identity/session');

        $response->assertOk()
            ->assertJsonPath('data.session_id', 'sess_new_expired')
            ->assertJsonPath('data.session_url', 'https://didit.test/session-new-expired');

        $this->assertDatabaseHas('customer_didit_sessions', [
            'customer_id' => $customer->id,
            'session_id' => 'sess_new_expired',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://didit.test/session'
            && ($request->data()['vendor_data'] ?? null) === DiditVendorData::forCustomer($customer->id));
    }

    public function test_abandoned_session_creates_a_new_didit_session(): void
    {
        $this->fakeDiditSessionCreate('sess_new_abandoned', 'https://didit.test/session-new-abandoned');

        $customer = $this->createPendingRetailCustomer();
        $this->seedSession($customer, 'sess_old_abandoned', 'Abandoned', 'https://didit.test/session-old-abandoned');

        $result = app(IdentityVerificationService::class)->ensureValidIndividualDocument($customer);

        $this->assertSame('success', $result['status']);
        $this->assertSame('sess_new_abandoned', $result['session']['session_id'] ?? null);
        Http::assertSentCount(1);
    }

    public function test_in_progress_session_is_reused_without_calling_didit(): void
    {
        Http::fake();

        $customer = $this->createPendingRetailCustomer();
        $this->seedSession($customer, 'sess_in_progress', 'In Progress', 'https://didit.test/session-in-progress');

        $result = app(IdentityVerificationService::class)->ensureValidIndividualDocument($customer);

        $this->assertSame('success', $result['status']);
        $this->assertSame('sess_in_progress', $result['session']['session_id'] ?? null);
        $this->assertSame('https://didit.test/session-in-progress', $result['session']['session_url'] ?? null);
        Http::assertNothingSent();
    }

    public function test_onboarding_resolver_returns_new_session_url_when_latest_is_expired(): void
    {
        $this->fakeDiditSessionCreate('sess_login_new', 'https://didit.test/session-login-new');

        $customer = $this->createPendingRetailCustomer();
        $this->seedSession($customer, 'sess_login_expired', 'Expired', 'https://didit.test/session-login-expired');

        $status = app(OnboardingStatusResolver::class)->resolveForCustomer($customer);
        $payload = $status->identityVerificationForAuth();

        $this->assertNotNull($payload);
        $this->assertSame('Not Started', $payload['status']);
        $this->assertSame('sess_login_new', $payload['session']['session_id'] ?? null);
        $this->assertSame('https://didit.test/session-login-new', $payload['session']['session_url'] ?? null);
        Http::assertSentCount(1);
    }

    private function createPendingRetailCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION,
            'doc_status' => DocumentStatus::PENDING->value,
        ]);
    }

    private function seedSession(Customer $customer, string $sessionId, string $status, string $sessionUrl): CustomerDiditSession
    {
        return CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => $sessionId,
            'status' => $status,
            'workflow_id' => 'wf_kyc_test',
            'session_url' => $sessionUrl,
        ]);
    }

    private function fakeDiditSessionCreate(string $sessionId, string $url): void
    {
        Http::fake([
            'https://didit.test/session' => Http::response([
                'session_id' => $sessionId,
                'url' => $url,
                'status' => 'Not Started',
                'workflow_id' => 'wf_kyc_test',
            ], 201),
        ]);
    }
}

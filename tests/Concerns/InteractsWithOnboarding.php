<?php

namespace Tests\Concerns;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Services\WallesterService;
use App\Models\Customer;
use App\Models\CustomerDiditSession;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerStripeAccount;
use App\Models\CustomerWallesterAccount;
use App\Models\Setting;

trait InteractsWithOnboarding
{
    protected function seedOnboardingSettings(): void
    {
        $settings = [
            'sms.base_url' => 'http://sms.test',
            'sms.key' => 'test-key',
            'sms.secret' => 'test-secret',
            'sms.auth_token' => 'test-token',
            'didit.api_key' => 'didit-test-key',
            'didit.api_secret' => 'didit-test-secret',
            'didit.base_url' => 'https://didit.test',
            'didit.workflow_id' => 'wf_kyc_test',
            'didit.workflow_kyb_id' => 'wf_kyb_test',
            'didit.webhook_secret' => 'didit-webhook-secret',
            'url.frontend' => 'http://localhost',
            'company.name' => 'Yoop Test',
            'company.email' => 'test@yoop.test',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    protected function createRetailCustomer(array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'type' => 'retail',
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function createEburyAccount(
        Customer $customer,
        IntegrationAccountStatus $status = IntegrationAccountStatus::PENDING,
        ?string $externalId = null,
    ): CustomerEburyAccount {
        return CustomerEburyAccount::query()->create([
            'customer_id' => $customer->id,
            'ebury_account_id' => $externalId ?? 'sim:'.$customer->id,
            'currency' => 'EUR',
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    protected function createWallesterAccount(
        Customer $customer,
        IntegrationAccountStatus $status = IntegrationAccountStatus::ACTIVE,
        ?string $externalId = null,
    ): CustomerWallesterAccount {
        $suffix = (string) $customer->id;

        return CustomerWallesterAccount::query()->create([
            'customer_id' => $customer->id,
            'wallester_account_id' => $externalId ?? 'wal_'.$suffix,
            'currency_code' => 'EUR_'.$suffix,
            'product_id' => 'prod_'.$suffix,
            'company_id' => 'comp_'.$suffix,
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    protected function createStripeAccount(
        Customer $customer,
        IntegrationAccountStatus $status = IntegrationAccountStatus::ACTIVE,
        ?string $externalId = null,
    ): CustomerStripeAccount {
        return CustomerStripeAccount::query()->create([
            'customer_id' => $customer->id,
            'stripe_customer_id' => $externalId ?? 'cus_'.$customer->id,
            'currency' => 'EUR',
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    protected function createDiditSession(Customer $customer, string $sessionId = 'sess_test_1'): CustomerDiditSession
    {
        return CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => $sessionId,
            'status' => 'In Progress',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://didit.test/session',
        ]);
    }

    protected function mockFinancialPartnerApis(): void
    {
        $this->mock(WallesterService::class, function ($mock) {
            $mock->shouldReceive('createAccount')
                ->andReturnUsing(fn () => ['id' => 'wal_api_'.uniqid()]);
        });

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')
                ->andReturnUsing(fn () => ['id' => 'cus_api_'.uniqid()]);
        });
    }

    protected function mockSmsGateway(): void
    {
        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(['status' => 'ok', 'body' => null, 'http_code' => 200]);
        });
    }

    protected function prepareOnboardingFeatureTest(): void
    {
        $this->seedOnboardingSettings();
        $this->mockFinancialPartnerApis();
        $this->mockSmsGateway();
    }

    /** @return array<string, mixed> */
    protected function diditApprovedPayload(int $customerId, string $sessionId, string $documentNumber = 'DOC123'): array
    {
        return [
            'session_id' => $sessionId,
            'decision' => [
                'status' => 'Approved',
                'id_verifications' => [
                    [
                        'document_number' => $documentNumber,
                        'first_name' => 'Test',
                        'last_name' => 'User',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function diditUserDataUpdatedPayload(
        string $applicationId,
        string $vendorUserId,
        string $status = 'Declined',
        array $overrides = [],
    ): array {
        return array_replace_recursive([
            'application_id' => $applicationId,
            'vendor_user_id' => $vendorUserId,
            'webhook_type' => 'user.data.updated',
            'status' => $status,
            'event_id' => 'evt_'.uniqid(),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    protected function diditStatusUpdatedPayload(
        string $applicationId,
        string $sessionId,
        string $status = 'Declined',
        array $decisionOverrides = [],
        array $overrides = [],
    ): array {
        return array_replace_recursive([
            'application_id' => $applicationId,
            'session_id' => $sessionId,
            'status' => $status,
            'webhook_type' => 'status.updated',
            'event_id' => 'evt_'.uniqid(),
            'workflow_id' => 'e7f13874-c89f-4cc2-9c20-a3aa9e05bcce',
            'decision' => array_replace_recursive([
                'session_id' => $sessionId,
                'status' => $status,
                'id_verifications' => [],
            ], $decisionOverrides),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    protected function diditRealDeclinedStatusPayload(string $applicationId, string $sessionId): array
    {
        return [
            'application_id' => $applicationId,
            'session_id' => $sessionId,
            'status' => 'Declined',
            'webhook_type' => 'status.updated',
            'event_id' => 'evt_declined_real_'.uniqid(),
            'workflow_id' => 'e7f13874-c89f-4cc2-9c20-a3aa9e05bcce',
            'decision' => [
                'session_id' => $sessionId,
                'status' => 'Declined',
                'id_verifications' => [
                    [
                        'address' => null,
                        'document_number' => null,
                        'document_subtype' => 'ID_CARD_GENERIC',
                        'document_type' => 'Identity Card',
                        'first_name' => null,
                        'full_name' => null,
                        'gender' => 'U',
                        'issuing_state' => 'ITA',
                        'issuing_state_name' => 'Italy',
                        'last_name' => null,
                        'marital_status' => 'UNKNOWN',
                        'portrait_image' => 'https://didit.test/portrait.jpg',
                        'front_image' => 'https://didit.test/front.jpg',
                        'back_image' => 'https://didit.test/back.jpg',
                        'status' => 'Declined',
                        'warnings' => [
                            [
                                'feature' => 'ID_VERIFICATION',
                                'log_type' => 'error',
                                'risk' => 'SCREEN_CAPTURE_DETECTED',
                                'short_description' => 'Screen capture of document detected',
                                'long_description' => 'Our document liveness detection has identified a screen capture.',
                            ],
                            [
                                'feature' => 'ID_VERIFICATION',
                                'log_type' => 'warning',
                                'risk' => 'DOCUMENT_NUMBER_NOT_DETECTED',
                                'short_description' => 'Document number not detected',
                                'long_description' => 'The system was unable to locate or read the document number.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

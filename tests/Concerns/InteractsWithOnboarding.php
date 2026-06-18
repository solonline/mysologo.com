<?php

namespace Tests\Concerns;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Models\Customer;
use App\Models\CustomerDiditSession;
use App\Models\CustomerEburyAccount;
use App\Models\CustomerStripeAccount;
use App\Models\CustomerWallesterAccount;
use App\Models\Setting;
use App\Domains\Stripe\Services\StripeService;
use App\Domains\Wallester\Services\WallesterService;

trait InteractsWithOnboarding
{
    protected function seedOnboardingSettings(): void
    {
        $settings = [
            'sms.base_url' => 'http://sms.test',
            'sms.key' => 'test-key',
            'sms.secret' => 'test-secret',
            'sms.auth_token' => 'test-token',
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
            'ebury_account_id' => $externalId ?? 'sim:' . $customer->id,
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
            'wallester_account_id' => $externalId ?? 'wal_' . $suffix,
            'currency_code' => 'EUR_' . $suffix,
            'product_id' => 'prod_' . $suffix,
            'company_id' => 'comp_' . $suffix,
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
            'stripe_customer_id' => $externalId ?? 'cus_' . $customer->id,
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
                ->andReturnUsing(fn () => ['id' => 'wal_api_' . uniqid()]);
        });

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')
                ->andReturnUsing(fn () => ['id' => 'cus_api_' . uniqid()]);
        });
    }

    protected function mockSmsGateway(): void
    {
        $this->mock(\App\Domains\Sms\Contracts\SmsGatewayInterface::class, function ($mock) {
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
}

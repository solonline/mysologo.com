<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\Customer;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerDashboardShowTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view');
        Permission::findOrCreate('customers.edit');
    }

    public function test_customer_show_displays_didit_session_and_kyc_warnings(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = Customer::factory()->create([
            'name' => 'Mario Rossi',
            'status' => CustomerLifecycleStatus::IDENTITY_REJECTED,
            'status_reason' => 'Identidade recusada pelo provedor de verificação.',
            'doc_status' => 'declined',
        ]);

        CustomerDiditSession::query()->create([
            'customer_id' => $customer->id,
            'session_id' => '9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            'status' => 'Declined',
            'workflow_id' => 'wf_test',
            'session_url' => 'https://verify.didit.me/en/session/test',
            'metadata' => [
                'application_id' => 'c159a625-9dde-444c-990d-9accf2298448',
                'session_id' => '9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            ],
        ]);

        CustomerRetailDocument::query()->create([
            'customer_id' => $customer->id,
            'document_number' => 'didit:9e06e806-4b55-4b79-b7fa-792287ae7ed8',
            'document_type' => 'Identity Card',
            'issuing_state_name' => 'Italy',
            'extra_fields' => [
                'status' => 'Declined',
                'document_subtype' => 'ID_CARD_GENERIC',
                'warnings' => [
                    [
                        'log_type' => 'error',
                        'risk' => 'SCREEN_CAPTURE_DETECTED',
                        'short_description' => 'Screen capture of document detected',
                        'long_description' => 'Document appears to be a screen capture.',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Verificação Didit');
        $response->assertSee('9e06e806-4b55-4b79-b7fa-792287ae7ed8');
        $response->assertSee('c159a625-9dde-444c-990d-9accf2298448');
        $response->assertSee('Motivos da verificação');
        $response->assertSee('Screen capture of document detected');
        $response->assertSee('SCREEN_CAPTURE_DETECTED');
        $response->assertSee('ID_CARD_GENERIC');
        $response->assertSee('Identidade recusada pelo provedor de verificação.');
    }

    public function test_customer_show_displays_stripe_funding_iban_when_active(): void
    {
        $user = $this->userWithPermission('customers.view');

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_show_funding', [
            'funding_status' => StripeFundingStatus::ACTIVE,
            'funding_iban' => 'DE89370400440532013000',
            'funding_bic' => 'COBADEFFXXX',
            'funding_account_holder_name' => 'Mario Rossi',
            'bank_transfer_type' => 'eu_bank_transfer',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('IBAN de Funding (Stripe)');
        $response->assertSee('DE89370400440532013000');
        $response->assertSee('COBADEFFXXX');
        $response->assertSee('Mario Rossi');
        $response->assertSee('Activo');
    }

    public function test_customer_show_displays_stripe_funding_failure_and_retry_button(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['customers.view', 'customers.edit']);

        $customer = Customer::factory()->create(['doc_status' => 'approved']);
        $this->createStripeAccount($customer, IntegrationAccountStatus::ACTIVE, 'cus_show_failed', [
            'funding_status' => StripeFundingStatus::FAILED,
            'funding_status_reason' => 'Stripe API unavailable',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('IBAN de Funding (Stripe)');
        $response->assertSee('A criação do IBAN falhou.');
        $response->assertSee('Stripe API unavailable');
        $response->assertSee('Repetir criação do IBAN');
        $response->assertSee(route('dashboard.customers.retry-stripe-funding', $customer), false);
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}

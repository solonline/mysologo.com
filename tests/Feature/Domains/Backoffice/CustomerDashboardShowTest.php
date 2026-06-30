<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerDashboardShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}

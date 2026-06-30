<?php

namespace Tests\Feature\Domains\Settings;

use App\Domains\Settings\Services\DiditSettingsService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DiditSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedDiditSettings();
    }

    public function test_didit_settings_service_prefers_database_over_env(): void
    {
        config([
            'services.didit.api_key' => 'env-key',
            'services.didit.workflow_kyb_id' => 'env-kyb',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'didit.api_key'],
            ['value' => 'db-key', 'type' => 'secret', 'label' => 'API Key'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'didit.workflow_kyb_id'],
            ['value' => 'db-kyb', 'type' => 'text', 'label' => 'Workflow KYB'],
        );

        $service = app(DiditSettingsService::class);

        $this->assertSame('db-key', $service->apiKey());
        $this->assertSame('db-kyb', $service->workflowKybId());
        $this->assertTrue($service->isKybWorkflow('db-kyb'));
        $this->assertFalse($service->isKybWorkflow('wf_kyc_test'));
    }

    public function test_didit_settings_service_falls_back_to_env_when_database_empty(): void
    {
        Setting::query()->where('key', 'like', 'didit.%')->delete();

        config([
            'services.didit.api_key' => 'env-only-key',
            'services.didit.workflow_id' => 'env-kyc',
            'services.didit.workflow_kyb_id' => 'env-kyb',
        ]);

        $service = app(DiditSettingsService::class);

        $this->assertSame('env-only-key', $service->apiKey());
        $this->assertSame('env-kyc', $service->workflowId());
        $this->assertSame('env-kyb', $service->workflowKybId());
    }

    public function test_dashboard_settings_page_shows_didit_tab_and_saves_values(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->actingAs($user)
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Didit')
            ->assertSee('Workflow KYB');

        $this->actingAs($user)
            ->post(route('dashboard.settings.update'), [
                'settings' => [
                    'didit.api_key' => 'dashboard-key',
                    'didit.workflow_kyb_id' => 'dashboard-kyb',
                ],
            ])
            ->assertRedirect(route('dashboard.settings'));

        $this->assertSame('dashboard-key', Setting::query()->where('key', 'didit.api_key')->value('value'));
        $this->assertSame('dashboard-kyb', Setting::query()->where('key', 'didit.workflow_kyb_id')->value('value'));
    }

    private function seedDiditSettings(): void
    {
        $settings = [
            ['key' => 'didit.api_key', 'label' => 'API Key', 'type' => 'secret', 'value' => 'seed-key'],
            ['key' => 'didit.api_secret', 'label' => 'API Secret', 'type' => 'secret', 'value' => 'seed-secret'],
            ['key' => 'didit.base_url', 'label' => 'Base URL', 'type' => 'url', 'value' => 'https://didit.test'],
            ['key' => 'didit.workflow_id', 'label' => 'Workflow KYC', 'type' => 'text', 'value' => 'wf_kyc_test'],
            ['key' => 'didit.workflow_kyb_id', 'label' => 'Workflow KYB', 'type' => 'text', 'value' => 'wf_kyb_test'],
            ['key' => 'didit.webhook_secret', 'label' => 'Webhook Secret', 'type' => 'secret', 'value' => 'seed-webhook'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                ],
            );
        }
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

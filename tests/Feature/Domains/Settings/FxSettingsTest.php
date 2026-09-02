<?php

namespace Tests\Feature\Domains\Settings;

use App\Domains\Fx\Services\FxSettingsService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FxSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedFxSettings();
    }

    public function test_fx_settings_service_prefers_database_over_env(): void
    {
        config([
            'services.fx.base_url' => 'https://env.awesomeapi.test',
            'services.fx.api_key' => 'env-key',
            'services.fx.cache_ttl_seconds' => 30,
            'services.fx.percentage' => '0.99',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'fx.base_url'],
            ['value' => 'https://economia.awesomeapi.com.br', 'type' => 'url', 'label' => 'URL Base'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'fx.api_key'],
            ['value' => 'db-key', 'type' => 'secret', 'label' => 'API Key (x-api-key)'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'fx.cache_ttl_seconds'],
            ['value' => '120', 'type' => 'number', 'label' => 'Cache TTL (segundos)'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'fx.percentage'],
            ['value' => '0.01', 'type' => 'text', 'label' => 'Percentagem da plataforma'],
        );

        $service = app(FxSettingsService::class);

        $this->assertSame('https://economia.awesomeapi.com.br', $service->baseUrl());
        $this->assertSame('db-key', $service->apiKey());
        $this->assertSame(120, $service->cacheTtlSeconds());
        $this->assertSame(0.01, $service->percentage());
    }

    public function test_fx_settings_service_falls_back_to_env_when_database_empty(): void
    {
        Setting::query()->where('key', 'like', 'fx.%')->delete();

        config([
            'services.fx.base_url' => 'https://env.awesomeapi.test/',
            'services.fx.api_key' => 'env-only-key',
            'services.fx.cache_ttl_seconds' => 45,
            'services.fx.percentage' => '0.015',
        ]);

        $service = app(FxSettingsService::class);

        $this->assertSame('https://env.awesomeapi.test', $service->baseUrl());
        $this->assertSame('env-only-key', $service->apiKey());
        $this->assertSame(45, $service->cacheTtlSeconds());
        $this->assertSame(0.015, $service->percentage());
    }

    public function test_fx_percentage_empty_or_zero_means_no_platform_fee(): void
    {
        config(['services.fx.percentage' => '']);

        $service = app(FxSettingsService::class);

        $this->assertSame(0.0, $service->percentage());

        Setting::query()->updateOrCreate(
            ['key' => 'fx.percentage'],
            ['value' => '0', 'type' => 'text', 'label' => 'Percentagem da plataforma'],
        );

        $this->assertSame(0.0, $service->percentage());
    }

    public function test_dashboard_settings_page_shows_fx_tab_and_saves_api_key(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->withoutVite()
            ->actingAs($user, 'web')
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Câmbio')
            ->assertSee('API Key (x-api-key)')
            ->assertSee('Percentagem da plataforma');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.settings.update'), [
                'settings' => [
                    'fx.base_url' => 'https://economia.awesomeapi.com.br',
                    'fx.api_key' => 'dashboard-token',
                    'fx.cache_ttl_seconds' => '90',
                    'fx.percentage' => '0.01',
                ],
            ])
            ->assertRedirect(route('dashboard.settings'));

        $this->assertSame('https://economia.awesomeapi.com.br', Setting::query()->where('key', 'fx.base_url')->value('value'));
        $this->assertSame('dashboard-token', Setting::query()->where('key', 'fx.api_key')->value('value'));
        $this->assertSame('90', Setting::query()->where('key', 'fx.cache_ttl_seconds')->value('value'));
        $this->assertSame('0.01', Setting::query()->where('key', 'fx.percentage')->value('value'));
    }

    private function seedFxSettings(): void
    {
        $settings = [
            ['key' => 'fx.base_url', 'label' => 'URL Base', 'type' => 'url', 'value' => 'https://economia.awesomeapi.com.br'],
            ['key' => 'fx.api_key', 'label' => 'API Key (x-api-key)', 'type' => 'secret', 'value' => ''],
            ['key' => 'fx.cache_ttl_seconds', 'label' => 'Cache TTL (segundos)', 'type' => 'number', 'value' => '60'],
            ['key' => 'fx.percentage', 'label' => 'Percentagem da plataforma', 'type' => 'text', 'value' => ''],
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
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}

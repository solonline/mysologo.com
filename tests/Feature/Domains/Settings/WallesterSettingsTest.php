<?php

namespace Tests\Feature\Domains\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterSettingsTest extends TestCase
{
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedWallesterSettings();
    }

    public function test_dashboard_settings_page_shows_wallester_tab_and_saves_values(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Wallester')
            ->assertSee('API Key')
            ->assertSee('id="retry-wallester-topups-form"', false)
            ->assertSee('form="retry-wallester-topups-form"', false)
            ->assertSee('Actualizar main e reprocessar top-ups');

        $this->actingAs($user)
            ->post(route('dashboard.settings.update'), [
                'settings' => [
                    'wallester.eur.api_key' => 'dashboard-eur-key',
                    'wallester.usd.product_code' => 'dashboard-usd-product',
                ],
            ])
            ->assertRedirect(route('dashboard.settings'));

        $this->assertSame('dashboard-eur-key', Setting::query()->where('key', 'wallester.eur.api_key')->value('value'));
        $this->assertSame('dashboard-usd-product', Setting::query()->where('key', 'wallester.usd.product_code')->value('value'));
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

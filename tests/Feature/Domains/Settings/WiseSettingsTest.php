<?php

namespace Tests\Feature\Domains\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WiseSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dashboard_settings_page_shows_wise_tab_and_saves_values(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->withoutVite()
            ->actingAs($user, 'web')
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Wise')
            ->assertSee('API Token')
            ->assertSee('Profile ID')
            ->assertSee('Webhook Public Key (PEM)');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.settings.update'), [
                'settings' => [
                    'wise.api_token' => 'dashboard-wise-token',
                    'wise.profile_id' => '998877',
                ],
            ])
            ->assertRedirect(route('dashboard.settings'));

        $this->assertSame('dashboard-wise-token', Setting::query()->where('key', 'wise.api_token')->value('value'));
        $this->assertSame('998877', Setting::query()->where('key', 'wise.profile_id')->value('value'));
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo(
            collect($permissions)->map(fn (string $permission) => Permission::findByName($permission, 'web'))->all()
        );

        return $user;
    }
}

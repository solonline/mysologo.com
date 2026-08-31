<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffUserCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('users.view', 'web');
        Permission::findOrCreate('users.create', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_create_page_is_not_captured_by_user_show_route(): void
    {
        $user = $this->userWithPermission('users.create');

        $this->actingAs($user, 'web')
            ->get(route('dashboard.users.create'))
            ->assertOk()
            ->assertSee('Novo Utilizador');
    }

    public function test_store_creates_staff_user_with_permission(): void
    {
        $user = $this->userWithPermission('users.create');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.users.store'), [
                'name' => 'Segundo Staff',
                'username' => 'segundo.staff',
                'email' => 'segundo.staff@yoop.test',
                'phone' => '351912000001',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'admin',
            ])
            ->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'username' => 'segundo.staff',
            'email' => 'segundo.staff@yoop.test',
        ]);

        $created = User::query()->where('username', 'segundo.staff')->first();
        $this->assertTrue($created?->hasRole('admin'));
    }

    public function test_create_page_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.users.create'))
            ->assertForbidden();
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName($permission, 'web'));

        return $user;
    }
}

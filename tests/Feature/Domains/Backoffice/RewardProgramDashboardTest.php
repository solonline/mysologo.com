<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Reward\Enums\RewardStatus;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RewardProgramDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('rewards.view', 'web');
        Permission::findOrCreate('rewards.create', 'web');
        Permission::findOrCreate('rewards.edit', 'web');
        Permission::findOrCreate('rewards.delete', 'web');
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.reward-programs'))
            ->assertForbidden();
    }

    public function test_index_lists_programs_with_permission(): void
    {
        $user = $this->userWithPermission('rewards.view');

        $this->actingAs($user, 'web')
            ->get(route('dashboard.reward-programs'))
            ->assertOk()
            ->assertSee('Recompensas')
            ->assertSee('Indicação')
            ->assertSee('referral');
    }

    public function test_store_creates_program_and_redirects_to_show(): void
    {
        $user = $this->userWithPermission('rewards.create');

        $response = $this->actingAs($user, 'web')
            ->post(route('dashboard.reward-programs.store'), [
                'slug' => 'welcome',
                'name' => 'Boas-vindas',
                'description' => 'Bónus de abertura de conta.',
                'is_active' => '1',
            ]);

        $program = RewardProgram::query()->where('slug', 'welcome')->first();
        $this->assertNotNull($program);
        $response->assertRedirect(route('dashboard.reward-programs.show', $program));
        $this->assertSame('Boas-vindas', $program->name);
        $this->assertTrue($program->is_active);
        $this->assertNull($program->valid_until);
    }

    public function test_show_lists_only_rewards_of_that_program(): void
    {
        $user = $this->userWithPermission('rewards.view');
        $referral = RewardProgram::referral();
        $welcome = RewardProgram::query()->create([
            'slug' => 'welcome',
            'name' => 'Boas-vindas',
            'is_active' => true,
        ]);
        $referrer = Customer::factory()->create(['status' => CustomerLifecycleStatus::ACTIVE]);
        $referred = Customer::factory()->create([
            'name' => 'Indicado Dashboard',
            'username' => 'indicado_dash',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'referred_by_customer_id' => $referrer->id,
        ]);

        Reward::query()->create([
            'customer_id' => $referrer->id,
            'program_id' => $referral->id,
            'referred_id' => $referred->id,
            'status' => RewardStatus::Eligible,
            'qualified_at' => now(),
        ]);
        Reward::query()->create([
            'customer_id' => $referrer->id,
            'program_id' => $welcome->id,
            'status' => RewardStatus::Pending,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.reward-programs.show', $referral))
            ->assertOk()
            ->assertSee('Indicação')
            ->assertSee('Indicado Dashboard')
            ->assertDontSee('Boas-vindas');
    }

    public function test_update_changes_name_and_keeps_slug(): void
    {
        $user = $this->userWithPermission('rewards.edit');
        $program = RewardProgram::referral();

        $this->actingAs($user, 'web')
            ->put(route('dashboard.reward-programs.update', $program), [
                'name' => 'Indicação Yoop',
                'description' => 'Termos actualizados.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('dashboard.reward-programs.show', $program));

        $program->refresh();
        $this->assertSame('referral', $program->slug);
        $this->assertSame('Indicação Yoop', $program->name);
        $this->assertSame('Termos actualizados.', $program->description);
    }

    public function test_cannot_delete_referral_program(): void
    {
        $user = $this->userWithPermission('rewards.delete');
        $program = RewardProgram::referral();

        $this->actingAs($user, 'web')
            ->from(route('dashboard.reward-programs.show', $program))
            ->delete(route('dashboard.reward-programs.destroy', $program))
            ->assertRedirect(route('dashboard.reward-programs.show', $program));

        $this->assertDatabaseHas('reward_programs', ['slug' => 'referral']);
    }

    public function test_deletes_empty_custom_program(): void
    {
        $user = $this->userWithPermission('rewards.delete');
        $program = RewardProgram::query()->create([
            'slug' => 'campaign_x',
            'name' => 'Campanha X',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web')
            ->delete(route('dashboard.reward-programs.destroy', $program))
            ->assertRedirect(route('dashboard.reward-programs'));

        $this->assertDatabaseMissing('reward_programs', ['id' => $program->id]);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName($permission, 'web'));

        return $user;
    }
}

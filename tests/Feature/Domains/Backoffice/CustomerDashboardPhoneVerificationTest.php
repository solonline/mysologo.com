<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Models\Customer;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerDashboardPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Permission::findOrCreate('customers.edit', 'web');

        $this->mock(SmsGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(['status' => 'success', 'body' => null, 'http_code' => 202]);
        });
    }

    public function test_show_displays_send_sms_button_when_phone_is_unverified(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.edit']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Não confirmado')
            ->assertSee('Enviar SMS')
            ->assertSee(route('dashboard.customers.phone.verify.send', $customer), false);
    }

    public function test_show_hides_send_sms_button_when_phone_is_verified(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.edit']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351912345678',
            'code' => '123456',
            'verified_at' => now(),
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Confirmado')
            ->assertDontSee('Enviar SMS');
    }

    public function test_show_hides_send_sms_button_without_edit_permission(): void
    {
        $user = $this->userWithPermissions(['customers.view']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Não confirmado')
            ->assertDontSee('Enviar SMS');
    }

    public function test_send_sms_creates_pending_verification_and_shows_confirm_form(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.edit']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.phone.verify.send', $customer))
            ->assertRedirect();

        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351912345678',
            'verified_at' => null,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Reenviar SMS')
            ->assertSee('Confirmar telefone')
            ->assertSee(route('dashboard.customers.phone.verify.confirm', $customer), false);
    }

    public function test_confirm_current_phone_marks_it_verified(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.edit']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351912345678',
            'code' => '654321',
            'verified_at' => null,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.phone.verify.confirm', $customer), [
                'code' => '654321',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('phone_verifications', [
            'customer_id' => $customer->id,
            'phone' => '351912345678',
        ]);
        $this->assertNotNull(
            PhoneVerification::query()
                ->where('customer_id', $customer->id)
                ->where('phone', '351912345678')
                ->whereNotNull('verified_at')
                ->first()
        );
    }

    public function test_confirm_accepts_code_after_resend_on_old_verification_row(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.edit']);
        $customer = Customer::factory()->create([
            'phone' => '351912345678',
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
        ]);

        PhoneVerification::query()->create([
            'customer_id' => $customer->id,
            'phone' => '351912345678',
            'code' => '111111',
            'verified_at' => null,
        ]);
        PhoneVerification::query()->where('customer_id', $customer->id)->update([
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(8),
        ]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.phone.verify.send', $customer))
            ->assertRedirect();

        $code = PhoneVerification::query()
            ->where('customer_id', $customer->id)
            ->where('phone', '351912345678')
            ->whereNull('verified_at')
            ->value('code');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.phone.verify.confirm', $customer), [
                'code' => $code,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    /** @param  list<string>  $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}

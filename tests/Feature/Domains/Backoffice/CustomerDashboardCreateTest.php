<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Auth\Mail\ValidationEmail;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerDashboardCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
        Permission::findOrCreate('customers.create', 'web');
    }

    public function test_create_form_requires_customers_create_permission(): void
    {
        $user = $this->userWithPermissions(['customers.view']);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.create'))
            ->assertForbidden();
    }

    public function test_create_form_is_visible_with_permission(): void
    {
        $user = $this->userWithPermissions(['customers.create']);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.create'))
            ->assertOk()
            ->assertSee('Novo Cliente')
            ->assertSee('Criar Cliente');
    }

    public function test_show_page_includes_create_button_when_permitted(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.create']);
        $customer = Customer::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Novo cliente')
            ->assertSee(route('dashboard.customers.create'), false);
    }

    public function test_show_page_hides_create_button_without_permission(): void
    {
        $user = $this->userWithPermissions(['customers.view']);
        $customer = Customer::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Novo cliente');
    }

    public function test_store_creates_customer_sends_validation_email_and_redirects_to_show(): void
    {
        Mail::fake();

        $user = $this->userWithPermissions(['customers.view', 'customers.create']);

        $response = $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.store'), [
                'name' => 'Carlos Mendes',
                'username' => 'Carlos Mendes',
                'email' => 'carlos@example.com',
                'phone' => '+351 912 345 678',
                'password' => 'password',
                'password_confirmation' => 'password',
                'type' => 'corporate',
                'language' => 'pt',
                'currency' => 'eur',
            ]);

        $customer = Customer::query()->where('email', 'carlos@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertSame('carlos_mendes', $customer->username);
        $this->assertSame('351912345678', $customer->phone);
        $this->assertSame('corporate', $customer->type);
        $this->assertSame('EUR', $customer->currency);
        $this->assertSame(CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION, $customer->status);
        $this->assertTrue(Hash::check('password', $customer->password));

        $response->assertRedirect(route('dashboard.customers.show', $customer));
        $response->assertSessionHas('success');

        Mail::assertSent(ValidationEmail::class, function (ValidationEmail $mail) use ($customer) {
            return $mail->hasTo($customer->email) && filled($mail->validationUrl);
        });
    }

    public function test_store_rejects_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'exists@example.com']);
        $user = $this->userWithPermissions(['customers.create']);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.create'))
            ->post(route('dashboard.customers.store'), [
                'name' => 'Outro',
                'username' => 'outro',
                'email' => 'exists@example.com',
                'phone' => '351911111111',
                'password' => 'password',
                'password_confirmation' => 'password',
                'type' => 'retail',
                'language' => 'pt',
                'currency' => 'EUR',
            ])
            ->assertRedirect(route('dashboard.customers.create'))
            ->assertSessionHasErrors('email');
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

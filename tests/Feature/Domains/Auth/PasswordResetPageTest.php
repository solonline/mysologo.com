<?php

namespace Tests\Feature\Domains\Auth;

use App\Domains\Auth\Services\PasswordResetService;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_link_shows_reset_form(): void
    {
        $customer = Customer::factory()->create();
        $token = app(PasswordResetService::class)->createToken($customer->email);

        $response = $this->get(route('password.reset', [
            'email' => $customer->email,
            'token' => $token,
        ]));

        $response->assertOk();
        $response->assertViewIs('auth.password-reset');
        $response->assertViewHas('status', 'form');
        $response->assertSee('Redefinir senha');
        $response->assertSee($customer->email);
    }

    public function test_missing_params_show_invalid_link(): void
    {
        $response = $this->get(route('password.reset'));

        $response->assertOk();
        $response->assertViewIs('auth.password-reset');
        $response->assertViewHas('status', 'invalid');
        $response->assertSee('Link inválido');
    }

    public function test_invalid_token_shows_invalid_link(): void
    {
        $customer = Customer::factory()->create();
        app(PasswordResetService::class)->createToken($customer->email);

        $response = $this->get(route('password.reset', [
            'email' => $customer->email,
            'token' => 'token-invalido',
        ]));

        $response->assertOk();
        $response->assertViewHas('status', 'invalid');
        $response->assertSee('Link inválido');
    }

    public function test_customer_can_reset_password_from_page(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('antiga-senha')]);
        $token = app(PasswordResetService::class)->createToken($customer->email);

        $response = $this->from(route('password.reset', [
            'email' => $customer->email,
            'token' => $token,
        ]))->post(route('password.update'), [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ]);

        $response->assertRedirect(route('password.reset.success'));

        $customer->refresh();
        $this->assertTrue(Hash::check('nova-senha-segura', $customer->password));
        $this->assertFalse(Hash::check('antiga-senha', $customer->password));

        $this->get(route('password.reset.success'))
            ->assertOk()
            ->assertSee('Senha atualizada')
            ->assertDontSee('Abrir a app');
    }

    public function test_success_page_requires_completed_reset(): void
    {
        $this->get(route('password.reset.success'))
            ->assertOk()
            ->assertSee('Link inválido')
            ->assertDontSee('Senha atualizada');
    }

    public function test_reset_rejects_mismatched_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $token = app(PasswordResetService::class)->createToken($customer->email);

        $response = $this->from(route('password.reset', [
            'email' => $customer->email,
            'token' => $token,
        ]))->post(route('password.update'), [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'outra-senha',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
    }

    public function test_password_reset_email_links_to_blade_page(): void
    {
        $customer = Customer::factory()->create();
        $token = 'plain-token-de-teste';
        $resetUrl = app(PasswordResetService::class)->resetUrl($customer->email, $token);

        $html = view('emails.password_reset', [
            'customer' => $customer,
            'token' => $token,
            'infoSystem' => ['company_name' => 'Yoop Test'],
            'resetUrl' => $resetUrl,
        ])->render();

        $this->assertStringContainsString(e($resetUrl), $html);
        $this->assertStringContainsString('/password/reset', $html);
        $this->assertStringContainsString(urlencode($customer->email), $html);
        $this->assertStringContainsString('Redefinir Senha', $html);
        $this->assertStringNotContainsString('app.yooppayments.eu/password/reset', $html);
    }
}

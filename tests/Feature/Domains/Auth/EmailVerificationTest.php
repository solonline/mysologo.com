<?php

namespace Tests\Feature\Domains\Auth;

use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
    }

    public function test_email_verification_shows_success_view_and_advances_lifecycle(): void
    {
        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $url = $this->signedVerificationUrl($customer);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('auth.email-verification-result');
        $response->assertViewHas('status', 'success');
        $response->assertViewHas('name', $customer->name);
        $response->assertSee('Email confirmado');
        $response->assertSee($customer->name);
        $response->assertSee('Abrir a app');
        $response->assertSee('com.yooppayments.app://', false);

        $customer->refresh();
        $this->assertNotNull($customer->email_verified_at);
        $this->assertSame(CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION, $customer->status);
    }

    public function test_email_verification_shows_already_verified_view(): void
    {
        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION,
            'email_verified_at' => now(),
        ]);

        $url = $this->signedVerificationUrl($customer);

        $response = $this->get($url);

        $response->assertConflict();
        $response->assertViewIs('auth.email-verification-result');
        $response->assertViewHas('status', 'already_verified');
        $response->assertViewHas('name', $customer->name);
        $response->assertSee('Email já confirmado');
    }

    public function test_email_verification_shows_not_found_view(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => 99999,
                'hash' => sha1('missing@example.com'),
            ]
        );

        $response = $this->get($url);

        $response->assertNotFound();
        $response->assertViewIs('auth.email-verification-result');
        $response->assertViewHas('status', 'not_found');
        $response->assertSee('Conta não encontrada');
    }

    public function test_email_validation_url_is_signed_for_the_frontend_host(): void
    {
        config(['app.url_frontend' => 'https://frontend.test']);

        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
            'email_verified_at' => null,
        ]);

        URL::forceRootUrl('https://api.yooppayments.eu');

        $url = app(CustomerProfileService::class)->generateEmailValidationUrl($customer);

        URL::forceRootUrl(null);

        $this->assertStringStartsWith(
            'https://frontend.test/email/verify/'.$customer->id.'/'.sha1($customer->email).'?',
            $url
        );

        $this->get($url)
            ->assertOk()
            ->assertViewHas('status', 'success');
    }

    public function test_email_verification_returns_json_when_requested(): void
    {
        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $url = $this->signedVerificationUrl($customer);

        $response = $this->getJson($url);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'name' => $customer->name,
        ]);
    }

    private function signedVerificationUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $customer->id,
                'hash' => sha1($customer->email),
            ]
        );
    }
}

<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Backoffice\Actions\CreateCustomerFromBackofficeAction;
use App\Domains\Backoffice\DTOs\CreateCustomerFromBackofficeData;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateCustomerFromBackofficeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_customer_with_pending_email_verification_and_dispatches_event(): void
    {
        Event::fake([UserRegistered::class]);

        $customer = app(CreateCustomerFromBackofficeAction::class)->execute(
            new CreateCustomerFromBackofficeData(
                name: 'Ana Silva',
                username: 'ana_silva',
                email: 'ana@example.com',
                phone: '351912345678',
                password: 'secret-password',
                type: 'retail',
                language: 'pt',
                currency: 'EUR',
            ),
        );

        $this->assertSame('Ana Silva', $customer->name);
        $this->assertSame('ana_silva', $customer->username);
        $this->assertSame('ana@example.com', $customer->email);
        $this->assertSame('351912345678', $customer->phone);
        $this->assertSame('retail', $customer->type);
        $this->assertSame('pt', $customer->language);
        $this->assertSame('EUR', $customer->currency);
        $this->assertSame(CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION, $customer->status);
        $this->assertTrue(Hash::check('secret-password', $customer->password));
        $this->assertNull($customer->email_verified_at);

        Event::assertDispatched(UserRegistered::class, function (UserRegistered $event) use ($customer) {
            return $event->customer->is($customer)
                && filled($event->validationUrl);
        });
    }
}

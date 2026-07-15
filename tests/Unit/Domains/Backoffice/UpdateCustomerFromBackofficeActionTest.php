<?php

namespace Tests\Unit\Domains\Backoffice;

use App\Domains\Backoffice\Actions\UpdateCustomerFromBackofficeAction;
use App\Domains\Backoffice\DTOs\UpdateCustomerFromBackofficeData;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCustomerFromBackofficeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_uses_lifecycle_transition_with_reason(): void
    {
        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::PROVISIONING,
            'status_reason' => 'Motivo anterior',
            'currency' => 'EUR',
        ]);

        app(UpdateCustomerFromBackofficeAction::class)->execute(
            $customer,
            new UpdateCustomerFromBackofficeData(
                name: $customer->name,
                email: $customer->email,
                currency: 'EUR',
                language: $customer->language,
                status: CustomerLifecycleStatus::BLOCKED->value,
                docStatus: $customer->doc_status,
            ),
        );

        $customer->refresh();

        $this->assertSame(CustomerLifecycleStatus::BLOCKED, $customer->status);
        $this->assertSame('Status alterado manualmente no backoffice.', $customer->status_reason);
    }

    public function test_profile_fields_update_without_touching_status(): void
    {
        $customer = Customer::factory()->create([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'status_reason' => null,
            'name' => 'Antigo',
        ]);

        app(UpdateCustomerFromBackofficeAction::class)->execute(
            $customer,
            new UpdateCustomerFromBackofficeData(
                name: 'Novo Nome',
                email: $customer->email,
                currency: 'EUR',
                language: 'pt',
                status: CustomerLifecycleStatus::ACTIVE->value,
                docStatus: $customer->doc_status,
            ),
        );

        $customer->refresh();

        $this->assertSame('Novo Nome', $customer->name);
        $this->assertNull($customer->status_reason);
    }
}

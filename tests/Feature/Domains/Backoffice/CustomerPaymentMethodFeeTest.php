<?php

namespace Tests\Feature\Domains\Backoffice;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use App\Models\CustomerPaymentMethodFee;
use App\Models\Merchant;
use App\Models\Tpa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class CustomerPaymentMethodFeeTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('customers.view', 'web');
    }

    public function test_staff_can_create_and_update_merchant_fee(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Taxas');

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->post(route('dashboard.customers.merchants.fees.store', [$customer, $merchant]), [
                'payment_method' => 'card',
                'label' => 'Cartão',
                'fee_percentage' => '0.0150',
                'fee_fixed' => '0.25',
                'currency' => 'eur',
                'is_active' => '1',
            ])
            ->assertRedirect(route('dashboard.customers.show', $customer));

        $this->assertDatabaseCount('customer_payment_method_fees', 1);
        $this->assertDatabaseHas('customer_payment_method_fees', [
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'tpa_id' => null,
            'payment_method' => 'card',
            'label' => 'Cartão',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.merchants.fees.store', [$customer, $merchant]), [
                'payment_method' => 'card',
                'label' => 'Cartão atualizado',
                'fee_percentage' => '0.0200',
                'fee_fixed' => '0.10',
                'currency' => 'EUR',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('customer_payment_method_fees', 1);
        $this->assertDatabaseHas('customer_payment_method_fees', [
            'merchant_id' => $merchant->id,
            'label' => 'Cartão atualizado',
            'fee_percentage' => '0.0200',
            'fee_fixed' => '0.1000',
        ]);
    }

    public function test_staff_cannot_store_fee_for_merchant_of_another_customer(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $otherCustomer = $this->createActiveCustomer();
        $otherMerchant = $this->createMerchant($otherCustomer, 'Loja Alheia');

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.merchants.fees.store', [$customer, $otherMerchant]), [
                'payment_method' => 'card',
                'label' => 'Cartão',
                'fee_percentage' => '0.0150',
                'fee_fixed' => '0.25',
                'currency' => 'EUR',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('customer_payment_method_fees', 0);
    }

    public function test_staff_can_delete_merchant_fee(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Delete');
        $fee = $this->createMerchantFee($customer, $merchant);

        $this->actingAs($user, 'web')
            ->from(route('dashboard.customers.show', $customer))
            ->delete(route('dashboard.customers.merchants.fees.destroy', [$customer, $merchant, $fee]))
            ->assertRedirect(route('dashboard.customers.show', $customer));

        $this->assertDatabaseMissing('customer_payment_method_fees', ['id' => $fee->id]);
    }

    public function test_find_active_for_merchant_returns_active_and_ignores_inactive(): void
    {
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Lookup');

        $inactive = $this->createMerchantFee($customer, $merchant, [
            'payment_method' => 'sepa_debit',
            'label' => 'SEPA inactiva',
            'is_active' => false,
        ]);

        $active = $this->createMerchantFee($customer, $merchant, [
            'payment_method' => 'card',
            'label' => 'Cartão activo',
            'is_active' => true,
        ]);

        $this->assertNull(CustomerPaymentMethodFee::findActiveForMerchant(
            $customer->id,
            $merchant->id,
            'sepa_debit',
            'EUR',
        ));

        $found = CustomerPaymentMethodFee::findActiveForMerchant(
            $customer->id,
            $merchant->id,
            'card',
            'eur',
        );

        $this->assertNotNull($found);
        $this->assertSame($active->id, $found->id);
        $this->assertNotSame($inactive->id, $found->id);
        $this->assertTrue($found->isMerchant());
        $this->assertFalse($found->isTpa());
        $this->assertFalse($found->isOnline());
    }

    public function test_merchant_fee_is_unique_per_customer_merchant_method_and_currency(): void
    {
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Unique');
        $this->createMerchantFee($customer, $merchant);

        $this->expectException(QueryException::class);

        $this->createMerchantFee($customer, $merchant, [
            'label' => 'Duplicado',
        ]);
    }

    public function test_tpa_fee_stays_isolated_from_merchant_fee(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Isolada');
        $tpa = Tpa::query()->create([
            'customer_id' => $customer->id,
            'serial' => 'TPA-FEE-'.uniqid(),
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.customers.tpas.fees.store', [$customer, $tpa]), [
                'payment_method' => 'card',
                'label' => 'TPA Cartão',
                'fee_percentage' => '0.0100',
                'fee_fixed' => '0.05',
                'currency' => 'EUR',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->createMerchantFee($customer, $merchant, [
            'payment_method' => 'card',
            'label' => 'Merchant Cartão',
            'fee_percentage' => 0.0250,
            'fee_fixed' => 0.40,
        ]);

        $this->assertDatabaseCount('customer_payment_method_fees', 2);

        $tpaFee = CustomerPaymentMethodFee::findActiveForTpa($customer->id, $tpa->id, 'card', 'EUR');
        $merchantFee = CustomerPaymentMethodFee::findActiveForMerchant($customer->id, $merchant->id, 'card', 'EUR');

        $this->assertNotNull($tpaFee);
        $this->assertNotNull($merchantFee);
        $this->assertNotSame($tpaFee->id, $merchantFee->id);
        $this->assertNull($tpaFee->merchant_id);
        $this->assertNull($merchantFee->tpa_id);
        $this->assertTrue($tpaFee->isTpa());
        $this->assertTrue($merchantFee->isMerchant());
        $this->assertSame('TPA Cartão', $tpaFee->label);
        $this->assertSame('Merchant Cartão', $merchantFee->label);
    }

    public function test_customer_show_displays_merchant_fees(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Show Fees');
        $this->createMerchantFee($customer, $merchant, [
            'label' => 'Taxa SecurePay Cartão',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Loja Show Fees')
            ->assertSee('Taxa SecurePay Cartão')
            ->assertDontSee('Adicionar Taxa');
    }

    public function test_customer_show_allows_adding_fee_for_another_currency(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Multi Moeda');
        $merchant->update(['allowed_currencies' => ['EUR', 'USD']]);
        $this->createMerchantFee($customer, $merchant, [
            'currency' => 'EUR',
            'label' => 'Taxa EUR',
        ]);

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Taxa EUR')
            ->assertSee('Adicionar Taxa')
            ->assertSee('USD', false)
            ->assertDontSee('Método de Pagamento');
    }

    public function test_customer_show_allows_adding_fee_when_merchant_has_none(): void
    {
        $user = $this->userWithPermission('customers.view');
        $customer = $this->createActiveCustomer();
        $this->createMerchant($customer, 'Loja Sem Taxa');

        $this->actingAs($user, 'web')
            ->get(route('dashboard.customers.show', $customer))
            ->assertOk()
            ->assertSee('Loja Sem Taxa')
            ->assertSee('Adicionar Taxa')
            ->assertSee('Nenhuma taxa personalizada');
    }

    private function createActiveCustomer(): Customer
    {
        return $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
            'currency' => 'EUR',
        ]);
    }

    private function createMerchant(Customer $customer, string $name): Merchant
    {
        return Merchant::query()->create([
            'customer_id' => $customer->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'api_key' => hash('sha256', 'yoop_test_fee_'.uniqid()),
            'mode' => 'test',
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createMerchantFee(
        Customer $customer,
        Merchant $merchant,
        array $overrides = [],
    ): CustomerPaymentMethodFee {
        return CustomerPaymentMethodFee::query()->create(array_merge([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'tpa_id' => null,
            'payment_method' => 'card',
            'label' => 'Cartão',
            'fee_percentage' => 0.0150,
            'fee_fixed' => 0.25,
            'currency' => 'EUR',
            'is_active' => true,
        ], $overrides));
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName($permission, 'web'));

        return $user;
    }
}

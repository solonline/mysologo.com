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
use Illuminate\Support\Facades\Route;
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

    public function test_merchant_fee_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('dashboard.customers.merchants.fees.store'));
        $this->assertFalse(Route::has('dashboard.customers.merchants.fees.destroy'));
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
        $customer = $this->createActiveCustomer();
        $merchant = $this->createMerchant($customer, 'Loja Isolada');
        $tpa = Tpa::query()->create([
            'customer_id' => $customer->id,
            'serial' => 'TPA-FEE-'.uniqid(),
            'status' => 'active',
        ]);

        CustomerPaymentMethodFee::query()->create([
            'customer_id' => $customer->id,
            'tpa_id' => $tpa->id,
            'payment_method' => 'card',
            'label' => 'TPA Cartão',
            'fee_percentage' => 0.0100,
            'fee_fixed' => 0.05,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

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

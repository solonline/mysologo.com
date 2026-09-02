<?php

namespace Database\Seeders;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Settings\DTOs\SettingDTO;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PlatformSubMasterAccountSeeder extends Seeder
{
    public const WALLESTER_ACCOUNT_ID = '626c80a3-f8e7-4627-8bcf-ca73f9944abf';

    public const ACCOUNT_NAME = 'Sub Conta Master';

    public const CUSTOMER_ID = 2;

    public const CUSTOMER_EMAIL = 'submaster@yoop.com';

    public const CUSTOMER_USERNAME = 'submaster';

    /**
     * php artisan db:seed --class=PlatformSubMasterAccountSeeder
     *
     * Cria o customer id 2 (Sub Conta Master) e a conta Wallester de taxas.
     * Sem Stripe.
     */
    public function run(): void
    {
        $customer = $this->seedCustomer();
        $this->seedWallesterAccount($customer);
        $this->syncFeeAccountIdToSettings('EUR', self::WALLESTER_ACCOUNT_ID);
    }

    private function seedCustomer(): Customer
    {
        $attributes = [
            'name' => self::ACCOUNT_NAME,
            'username' => self::CUSTOMER_USERNAME,
            'email' => self::CUSTOMER_EMAIL,
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => CustomerLifecycleStatus::ACTIVE,
            'doc_status' => 'approved',
            'email_verified_at' => now(),
        ];

        $customer = Customer::query()->find(self::CUSTOMER_ID);

        if ($customer instanceof Customer) {
            $customer->fill($attributes);
            $customer->save();

            return $customer->refresh();
        }

        $customer = new Customer($attributes);
        $customer->id = self::CUSTOMER_ID;
        $customer->save();

        return $customer->refresh();
    }

    private function seedWallesterAccount(Customer $customer): CustomerWallesterAccount
    {
        $main = Customer::query()
            ->where('type', 'platform')
            ->where('id', '!=', $customer->id)
            ->first()
            ?->platformWallesterMainAccounts()
            ->where('funding_currency', 'EUR')
            ->first();

        $fromApi = $this->fetchAccountFromApi($customer);
        $status = app(IntegrationAccountService::class)->mapExternalStatus(
            IntegrationProvider::WALLESTER,
            (string) ($fromApi['status'] ?? 'active'),
        );

        return CustomerWallesterAccount::query()->updateOrCreate(
            ['wallester_account_id' => self::WALLESTER_ACCOUNT_ID],
            [
                'customer_id' => $customer->id,
                'currency_code' => strtoupper((string) ($fromApi['currency_code'] ?? 'EUR')),
                'funding_currency' => 'EUR',
                'product_id' => (string) ($fromApi['product_id'] ?? $main?->product_id ?? 'prod_platform_fee_eur'),
                'company_id' => (string) ($fromApi['company_id'] ?? $main?->company_id ?? 'comp_platform_fee_eur'),
                'is_main' => false,
                'receiver_name' => self::ACCOUNT_NAME,
                'status' => $status,
                'status_source' => 'platform.fee_seeder',
                'status_changed_at' => now(),
                'balance' => $this->moneyToCents($fromApi['balance'] ?? null),
                'available_amount' => $this->moneyToCents($fromApi['available_amount'] ?? null),
                'blocked_amount' => $this->moneyToCents($fromApi['blocked_amount'] ?? null),
                'blocked_credit_amount' => $this->moneyToCents($fromApi['blocked_credit_amount'] ?? null),
                'credit_limit' => $this->moneyToCents($fromApi['credit_limit'] ?? null),
                'used_credit' => $this->moneyToCents($fromApi['used_credit'] ?? null),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAccountFromApi(Customer $customer): array
    {
        try {
            $account = app(WallesterGatewayInterface::class)
                ->forCurrency('EUR')
                ->getAccount(self::WALLESTER_ACCOUNT_ID, (string) $customer->id);

            return is_array($account) ? $account : [];
        } catch (\Throwable $exception) {
            Log::warning('PlatformSubMasterAccountSeeder: falha ao obter sub-conta Wallester.', [
                'account_id' => self::WALLESTER_ACCOUNT_ID,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function syncFeeAccountIdToSettings(string $currency, string $accountId): void
    {
        app(SettingsService::class)->set(new SettingDTO(
            key: 'wallester.'.strtolower($currency).'.platform_fee_account_id',
            value: $accountId,
        ));
    }

    private function moneyToCents(float|int|string|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return app(WallesterFundingTransferDriver::class)->amountToCents($amount);
    }
}

<?php

namespace Database\Seeders;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Domains\Settings\DTOs\SettingDTO;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use App\Domains\Wallester\Services\WallesterAccountService;
use App\Domains\Wallester\Services\WallesterFundingTransferDriver;
use App\Domains\Wallester\Services\WallesterSettingsService;
use App\Models\Customer;
use App\Models\CustomerWallesterAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PlatformAccountSeeder extends Seeder
{
    private const SUPPORTED_MAIN_CURRENCIES = ['EUR', 'USD'];

    private const DEFAULT_MASTER_ACCOUNTS = [
        'EUR' => '75d92dc4-6de9-4580-8b62-3cbd3513af56',
    ];

    /**
     * php artisan db:seed --class=PlatformAccountSeeder
     */
    public function run(): void
    {
        $customer = $this->seedPlatformCustomer();
        $this->seedPlatformWallesterMainAccounts($customer);
    }

    private function seedPlatformCustomer(): Customer
    {
        return Customer::query()->updateOrCreate(
            ['email' => 'master@yoop.com'],
            [
                'name' => 'Yoop Platform',
                'username' => 'platform',
                'type' => 'platform',
                'currency' => 'EUR',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedPlatformWallesterMainAccounts(Customer $customer): void
    {
        $mainAccounts = $this->resolveMainAccountsFromApi($customer);

        if ($mainAccounts === []) {
            $mainAccounts = $this->fallbackMainAccounts();
        }

        foreach ($mainAccounts as $mainAccount) {
            $this->upsertPlatformMainAccount($customer, $mainAccount);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveMainAccountsFromApi(Customer $customer): array
    {
        $accounts = [];
        $settings = app(WallesterSettingsService::class);
        $accountService = app(WallesterAccountService::class);
        $gateway = app(WallesterGatewayInterface::class);

        foreach (self::SUPPORTED_MAIN_CURRENCIES as $currency) {
            if (! $settings->hasCurrencyCredentials($currency)) {
                continue;
            }

            try {
                $response = $accountService->listForCustomer($customer, ['is_main' => true], $currency);

                foreach ($response['accounts'] as $account) {
                    if (($account['is_main'] ?? false) !== true) {
                        continue;
                    }

                    $accountCurrency = strtoupper((string) ($account['currency_code'] ?? $currency));
                    $routingCurrency = app(PlatformMasterCurrencyResolver::class)->resolve($accountCurrency);
                    $accountId = (string) ($account['id'] ?? '');

                    if ($accountId === '') {
                        continue;
                    }

                    $details = $this->fetchMasterAccountFromApi(
                        $gateway,
                        $customer,
                        $accountId,
                        $routingCurrency,
                    ) ?? $account;

                    $details['currency_code'] = $accountCurrency;
                    $details['funding_currency'] = $routingCurrency;
                    $accounts[] = $details;
                }
            } catch (\Throwable $exception) {
                Log::warning('PlatformAccountSeeder: falha ao listar contas main Wallester.', [
                    'currency' => $currency,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return collect($accounts)
            ->unique(fn (array $account) => $account['funding_currency'] ?? null)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackMainAccounts(): array
    {
        $accounts = [];
        $settings = app(WallesterSettingsService::class);

        foreach (self::SUPPORTED_MAIN_CURRENCIES as $currency) {
            $accountId = null;

            try {
                $accountId = $settings->platformMasterAccountId($currency);
            } catch (\Throwable) {
                $accountId = self::DEFAULT_MASTER_ACCOUNTS[$currency] ?? null;
            }

            if (! is_string($accountId) || $accountId === '') {
                continue;
            }

            $payload = $this->fallbackMasterAccountPayload($accountId, $currency);
            $payload['funding_currency'] = $currency;

            $accounts[] = $payload;
        }

        return $accounts;
    }

    /**
     * @param  array<string, mixed>  $wallesterAccount
     */
    private function upsertPlatformMainAccount(Customer $customer, array $wallesterAccount): void
    {
        $accountId = (string) ($wallesterAccount['id'] ?? '');

        if ($accountId === '') {
            return;
        }

        $routingCurrency = strtoupper((string) ($wallesterAccount['funding_currency'] ?? 'EUR'));
        $attributes = $this->mapWallesterAccountAttributes($wallesterAccount);
        $attributes['currency_code'] = strtoupper((string) ($wallesterAccount['currency_code'] ?? $routingCurrency));
        $attributes['funding_currency'] = $routingCurrency;
        $attributes['product_id'] = (string) ($wallesterAccount['product_id'] ?? 'prod_platform_'.$routingCurrency);
        $attributes['company_id'] = (string) ($wallesterAccount['company_id'] ?? 'comp_platform_'.$routingCurrency);
        $attributes['is_main'] = true;

        CustomerWallesterAccount::query()->updateOrCreate(
            ['wallester_account_id' => $accountId],
            [
                'customer_id' => $customer->id,
                ...$attributes,
            ],
        );

        $this->syncMasterAccountIdToSettings($routingCurrency, $accountId);
    }

    private function syncMasterAccountIdToSettings(string $currency, string $accountId): void
    {
        app(SettingsService::class)->set(new SettingDTO(
            key: 'wallester.'.strtolower($currency).'.platform_master_account_id',
            value: $accountId,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMasterAccountFromApi(
        WallesterGatewayInterface $gateway,
        Customer $customer,
        string $accountId,
        string $currency,
    ): ?array {
        try {
            return $gateway->forCurrency($currency)->getAccount($accountId, (string) $customer->id);
        } catch (\Throwable $exception) {
            Log::warning('PlatformAccountSeeder: falha ao obter conta Wallester.', [
                'account_id' => $accountId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackMasterAccountPayload(string $accountId, string $currency): array
    {
        if ($currency === 'USD') {
            return [
                'id' => $accountId,
                'currency_code' => 'USD',
                'name' => 'Yoop Platform USD Main',
                'status' => 'Active',
                'is_main' => true,
                'balance' => 0,
                'available_amount' => 0,
            ];
        }

        return [
            'id' => $accountId,
            'product_id' => '9fd2307c-5419-4ccd-a6f7-3d6007bcb4ad',
            'company_id' => '1d299458-b005-4f5f-bd5c-ad42640ddcea',
            'name' => 'TAMIETTO JUNIOR UNIPESSOAL LDA',
            'currency_code' => 'EUR',
            'credit_limit' => 0,
            'used_credit' => 0,
            'balance' => 0,
            'available_amount' => 0,
            'blocked_amount' => 0,
            'blocked_credit_amount' => 0,
            'status' => 'Active',
            'is_main' => true,
            'cards_count' => 0,
            'viban' => 'DK8789000053959870',
            'reference_number' => '24320897',
            'limits' => [
                'daily_purchase' => 15000,
                'daily_withdrawal' => 5000,
                'daily_internet_purchase' => 15000,
                'daily_contactless_purchase' => 15000,
                'weekly_purchase' => 25000,
                'weekly_withdrawal' => 15000,
                'weekly_internet_purchase' => 25000,
                'weekly_contactless_purchase' => 25000,
                'monthly_purchase' => 25000,
                'monthly_withdrawal' => 15000,
                'monthly_internet_purchase' => 25000,
                'monthly_contactless_purchase' => 25000,
            ],
            'top_up_details' => [
                'iban' => 'DK8789000053959870',
                'swift_code' => 'SXPYDKKK',
                'receiver_name' => 'TAMIETTO JUNIOR UNIPESSOAL LDA',
                'payment_details' => '24320897',
                'bank_name' => 'Banking Circle Denmark, Filial af Banking Circle S.A., Luxembourg',
                'bank_address' => 'Lautrupsgade 13-15 2100 Copenhagen, DENMARK',
                'registration_number' => '41023821',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $wallesterAccount
     * @return array<string, mixed>
     */
    private function mapWallesterAccountAttributes(array $wallesterAccount): array
    {
        $limits = is_array($wallesterAccount['limits'] ?? null) ? $wallesterAccount['limits'] : [];
        $topUp = is_array($wallesterAccount['top_up_details'] ?? null) ? $wallesterAccount['top_up_details'] : [];
        $integrationAccounts = app(IntegrationAccountService::class);

        $status = $integrationAccounts->mapExternalStatus(
            IntegrationProvider::WALLESTER,
            (string) ($wallesterAccount['status'] ?? 'active'),
        );

        return [
            'credit_limit' => $this->moneyToCents($wallesterAccount['credit_limit'] ?? null),
            'used_credit' => $this->moneyToCents($wallesterAccount['used_credit'] ?? null),
            'balance' => $this->moneyToCents($wallesterAccount['balance'] ?? null),
            'available_amount' => $this->moneyToCents($wallesterAccount['available_amount'] ?? null),
            'blocked_amount' => $this->moneyToCents($wallesterAccount['blocked_amount'] ?? null),
            'blocked_credit_amount' => $this->moneyToCents($wallesterAccount['blocked_credit_amount'] ?? null),
            'status' => $status,
            'status_reason' => null,
            'status_source' => 'platform.seeder',
            'status_changed_at' => now(),
            'daily_purchase' => $limits['daily_purchase'] ?? null,
            'daily_withdrawal' => $limits['daily_withdrawal'] ?? null,
            'daily_internet_purchase' => $limits['daily_internet_purchase'] ?? null,
            'daily_contactless_purchase' => $limits['daily_contactless_purchase'] ?? null,
            'weekly_purchase' => $limits['weekly_purchase'] ?? null,
            'weekly_withdrawal' => $limits['weekly_withdrawal'] ?? null,
            'weekly_internet_purchase' => $limits['weekly_internet_purchase'] ?? null,
            'weekly_contactless_purchase' => $limits['weekly_contactless_purchase'] ?? null,
            'monthly_purchase' => $limits['monthly_purchase'] ?? null,
            'monthly_withdrawal' => $limits['monthly_withdrawal'] ?? null,
            'monthly_internet_purchase' => $limits['monthly_internet_purchase'] ?? null,
            'monthly_contactless_purchase' => $limits['monthly_contactless_purchase'] ?? null,
            'cards_count' => (int) ($wallesterAccount['cards_count'] ?? 0),
            'iban' => $topUp['iban'] ?? null,
            'swift_code' => $topUp['swift_code'] ?? null,
            'receiver_name' => $topUp['receiver_name'] ?? ($wallesterAccount['name'] ?? null),
            'payment_details' => $topUp['payment_details'] ?? null,
            'bank_name' => $topUp['bank_name'] ?? null,
            'bank_address' => $topUp['bank_address'] ?? null,
            'registration_number' => $topUp['registration_number'] ?? null,
            'reference_number' => $wallesterAccount['reference_number'] ?? null,
            'viban' => $wallesterAccount['viban'] ?? ($topUp['iban'] ?? null),
        ];
    }

    private function moneyToCents(float|int|string|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return app(WallesterFundingTransferDriver::class)->amountToCents($amount);
    }
}

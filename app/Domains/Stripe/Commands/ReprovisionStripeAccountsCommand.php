<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Models\Customer;
use App\Models\CustomerStripeAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReprovisionStripeAccountsCommand extends Command
{
    protected $signature = 'stripe:reprovision-accounts
                            {--dry-run : Lista contas e customers ACTIVE sem escrever}
                            {--force : Apaga as contas locais e recria conta Stripe e IBAN}
                            {--funding-only : Não apaga contas. Pede funding instructions e grava o IBAN nas contas já existentes}';

    protected $description = 'Apaga customer_stripe_accounts e recria conta Stripe e IBAN para customers com onboarding concluído.';

    public function handle(FinancialProvisioningService $provisioning): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $fundingOnly = (bool) $this->option('funding-only');

        if (! $dryRun && ! $force && ! $fundingOnly) {
            $this->error('Recusa executar sem --dry-run, --force ou --funding-only.');

            return self::FAILURE;
        }

        if ($dryRun && ($force || $fundingOnly)) {
            $this->warn('--dry-run tem prioridade e não escreve.');
            $force = false;
            $fundingOnly = false;
        }

        if ($force && $fundingOnly) {
            $this->error('Usa só uma opção: --force apaga e recria; --funding-only só pede o IBAN.');

            return self::FAILURE;
        }

        $accounts = CustomerStripeAccount::withTrashed()->count();
        $customers = $this->eligibleCustomers();

        $this->info(sprintf(
            'Contas Stripe locais: %d. Customers ACTIVE a reprovisionar: %d.',
            $accounts,
            $customers->count(),
        ));

        if ($customers->isNotEmpty()) {
            $this->table(
                ['ID', 'Nome', 'E-mail'],
                $customers->map(fn (Customer $customer) => [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                ])->all(),
            );
        }

        if (! $force && ! $fundingOnly) {
            $this->comment('Dry-run — nenhuma conta foi apagada e a Stripe não foi chamada.');

            return self::SUCCESS;
        }

        if ($fundingOnly) {
            $this->comment('Funding only — as contas locais ficam. A seguir pede createCustomerFundingInstructions e grava o IBAN.');
        } else {
            $deleted = $this->deleteStripeAccounts();
            $this->info(sprintf('Contas locais apagadas: %d.', $deleted));
        }

        $accountsCreated = 0;
        $ibansCreated = 0;
        $failures = [];

        foreach ($customers as $customer) {
            $customer = $customer->fresh();

            if ($customer === null) {
                continue;
            }

            try {
                $provisioned = $provisioning->provisionStripe($customer);
            } catch (\Throwable $e) {
                $failures[] = [
                    'id' => $customer->id,
                    'step' => 'conta',
                    'reason' => $e->getMessage(),
                ];

                continue;
            }

            $account = $customer->fresh('stripeAccount')->stripeAccount;

            if (! $provisioned || $account === null) {
                $failures[] = [
                    'id' => $customer->id,
                    'step' => 'conta',
                    'reason' => $account?->status_reason ?? 'Falha ao criar a conta Stripe.',
                ];

                continue;
            }

            $accountsCreated++;

            if ($account->funding_status === StripeFundingStatus::ACTIVE && filled($account->funding_iban)) {
                $ibansCreated++;

                continue;
            }

            $failures[] = [
                'id' => $customer->id,
                'step' => 'iban',
                'reason' => $account->funding_status_reason ?? 'Conta criada, mas o IBAN não ficou ativo.',
            ];
        }

        $this->info(sprintf(
            'Contas criadas: %d. IBANs criados: %d. Falhas: %d.',
            $accountsCreated,
            $ibansCreated,
            count($failures),
        ));

        if ($failures !== []) {
            $this->table(
                ['Customer', 'Passo', 'Motivo'],
                $failures,
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Customer> */
    private function eligibleCustomers(): Collection
    {
        return Customer::query()
            ->where('status', CustomerLifecycleStatus::ACTIVE)
            ->where('type', '!=', 'platform')
            ->orderBy('id')
            ->get();
    }

    private function deleteStripeAccounts(): int
    {
        $deleted = 0;

        CustomerStripeAccount::withTrashed()
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use (&$deleted): void {
                foreach ($accounts as $account) {
                    /** @var CustomerStripeAccount $account */
                    $account->forceDelete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Stripe\Services\PurgeMerchantPaymentsService;
use Illuminate\Console\Command;

class PurgeMerchantPaymentsCommand extends Command
{
    protected $signature = 'stripe:purge-merchant-payments
                            {--except=30 : Stripe transaction id to keep}
                            {--dry-run : List candidates without writing}
                            {--force : Execute the purge}
                            {--cleanup-orphans : Delete SecurePay payment requests linked to soft-deleted merchant_payments}';

    protected $description = 'Soft-delete merchant_payment transactions (except one id), reversing pending ledger and platform fees.';

    public function __construct(
        private readonly PurgeMerchantPaymentsService $purgeMerchantPayments,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cleanupOrphans = (bool) $this->option('cleanup-orphans');
        $exceptId = (int) $this->option('except');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($cleanupOrphans) {
            if (! $force && ! $dryRun) {
                $this->error('Refuse to cleanup orphans without --dry-run or --force.');

                return self::FAILURE;
            }

            if ($dryRun) {
                $this->comment('Dry-run: orphan cleanup preview is applied via --force only; use --force --cleanup-orphans to delete.');
                $this->info('Orphan requests are those linked to soft-deleted merchant_payment transactions.');

                return self::SUCCESS;
            }

            $cleanup = $this->purgeMerchantPayments->cleanupOrphanedPaymentRequests();
            $this->info(sprintf('Deleted orphaned SecurePay requests: %d', $cleanup['deleted_requests']));
            if ($cleanup['payment_intent_ids'] !== []) {
                $this->line('PIs: '.implode(', ', $cleanup['payment_intent_ids']));
            }

            return self::SUCCESS;
        }

        if (! $dryRun && ! $force) {
            $this->error('Refuse to run without --dry-run or --force.');

            return self::FAILURE;
        }

        if ($dryRun && $force) {
            $this->warn('--dry-run takes precedence over --force.');
            $force = false;
            $dryRun = true;
        }

        $result = $this->purgeMerchantPayments->execute(
            exceptId: $exceptId,
            dryRun: $dryRun || ! $force,
        );

        $this->info(sprintf(
            'Purge merchant_payment (except=%d, dry_run=%s)',
            $result['except_id'],
            $result['dry_run'] ? 'yes' : 'no',
        ));

        if ($result['candidates'] === []) {
            $this->warn('No merchant_payment candidates found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Customer', 'Amount', 'Net', 'Platform fee', 'Funding', 'Pending ledger', 'Fee ledger'],
            collect($result['candidates'])->map(fn (array $row) => [
                $row['id'],
                $row['customer_id'],
                $row['amount'],
                $row['net_amount'],
                $row['platform_fee'],
                $row['partner_funding_status'] ?? '—',
                $row['pending_ledger'] ?? '—',
                $row['platform_fee_ledger'] ?? '—',
            ])->all(),
        );

        if ($result['dry_run']) {
            $this->comment('Dry-run only — no writes performed.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Purged=%d skipped=%d reversed_pending=%d reversed_fees=%d',
            $result['purged'],
            $result['skipped'],
            $result['reversed_pending_cents'],
            $result['reversed_fees_cents'],
        ));

        if ($result['balances'] !== []) {
            $this->newLine();
            $this->info('Customer balances after purge:');
            $this->table(
                ['Customer', 'Currency', 'Wallester allocated', 'Pending'],
                collect($result['balances'])->map(fn (array $row) => [
                    $row['customer_id'],
                    $row['currency'],
                    $row['wallester_allocated'],
                    $row['pending'],
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}

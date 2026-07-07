<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Commands;

use App\Domains\Wallet\Services\Audit\WalletAuditService;
use Illuminate\Console\Command;

class RunWalletAuditCommand extends Command
{
    protected $signature = 'wallet:audit
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--customer= : Filter by customer ID}
                            {--currency= : Filter by ISO currency code}
                            {--dry-run : Report findings only; do not apply fixes}
                            {--fix : Apply safe automatic fixes (not yet implemented)}';

    protected $description = 'Audit Stripe transactions, wallet balances, ledger, and Wallester top-up movements.';

    public function __construct(
        private readonly WalletAuditService $auditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fix = (bool) $this->option('fix');

        if ($fix && ! $dryRun) {
            $this->warn('--fix is not implemented yet. Re-run with --dry-run to inspect findings only.');

            return self::FAILURE;
        }

        if ($fix && $dryRun) {
            $this->warn('--fix with --dry-run: no corrections will be applied (fix mode is reserved for future use).');
        }

        $report = $this->auditService->run(
            from: $this->option('from') ? (string) $this->option('from') : null,
            to: $this->option('to') ? (string) $this->option('to') : null,
            customerId: $this->option('customer') ? (int) $this->option('customer') : null,
            currency: $this->option('currency') ? (string) $this->option('currency') : null,
        );

        $counts = $report->countsBySeverity();
        $stats = $this->auditService->scanStats(
            from: $this->option('from') ? (string) $this->option('from') : null,
            to: $this->option('to') ? (string) $this->option('to') : null,
            customerId: $this->option('customer') ? (int) $this->option('customer') : null,
            currency: $this->option('currency') ? (string) $this->option('currency') : null,
        );

        $this->info('Wallet audit complete.');
        $this->line(sprintf(
            'Scope: from=%s to=%s customer=%s currency=%s',
            $report->from ?? 'any',
            $report->to ?? 'any',
            $report->customerId !== null ? (string) $report->customerId : 'any',
            $report->currency ?? 'any',
        ));
        $this->line(sprintf(
            'Database: %s / %s',
            config('database.default'),
            config('database.connections.'.config('database.default').'.database'),
        ));
        $this->line(sprintf(
            'Scanned: %d stripe_transactions, %d customer_balances, %d ledger_entries',
            $stats['transactions'],
            $stats['balances'],
            $stats['ledger_entries'],
        ));

        if ($stats['transactions'] === 0 && $stats['balances'] === 0 && $stats['ledger_entries'] === 0) {
            $this->warn('No wallet data found in the active database. Audit passed vacuously — verify DB_DATABASE in .env matches the database you inspect.');

            return self::SUCCESS;
        }
        $this->line(sprintf(
            'Findings: critical=%d warning=%d info=%d',
            $counts['critical'],
            $counts['warning'],
            $counts['info'],
        ));

        if ($report->findings === []) {
            $this->info('No issues found.');

            return self::SUCCESS;
        }

        foreach ($report->findings as $finding) {
            $this->line('');
            $this->line("<fg=red>[$finding->severity]</> <fg=yellow>$finding->code</> $finding->message");
            $this->line(json_encode($finding->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $report->hasCritical() ? self::FAILURE : self::SUCCESS;
    }
}

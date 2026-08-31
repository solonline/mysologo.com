<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Wallet\Services\StripePayoutSettlementService;
use Illuminate\Console\Command;
use Throwable;

class SettleStripePayoutCommand extends Command
{
    protected $signature = 'stripe:settle-payout
                            {payoutId : ID Stripe do payout (po_...)}
                            {--test : Usar a conta Stripe de teste}
                            {--currency=eur : Moeda da conta Stripe}';

    protected $description = 'Liquida as transações locais confirmadas por um payout.paid da Stripe.';

    public function __construct(private readonly StripePayoutSettlementService $payoutSettlement)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $payoutId = (string) $this->argument('payoutId');
        $livemode = ! $this->option('test');
        $currency = strtolower((string) $this->option('currency'));

        $this->info("A liquidar {$payoutId} (".($livemode ? 'live' : 'test').", {$currency})...");

        try {
            $stats = $this->payoutSettlement->settlePayout($payoutId, $currency, $livemode);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Concluído — matched=%d wallester_dispatched=%d deferred_credited=%d missing=%d skipped=%d',
            $stats['matched'],
            $stats['wallester_dispatched'],
            $stats['deferred_credited'],
            $stats['missing'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}

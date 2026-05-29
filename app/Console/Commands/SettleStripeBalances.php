<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TransactionDirection;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Console\Command;

class SettleStripeBalances extends Command
{
    protected $signature = 'stripe:settle-balances';

    protected $description = 'Liberta saldos pendentes de transações Stripe cuja data de liquidação (available_on) já chegou.';

    public function __construct(private readonly StripeService $stripeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        Transaction::query()
            ->where('status', 'succeeded')
            ->where('direction', TransactionDirection::Credit->value)
            ->whereNotNull('available_on')
            ->whereDate('available_on', '<=', today())
            ->whereNull('balance_settled_at')
            ->whereNotIn('type', ['p2p'])
            ->chunkById(100, function ($transactions) use (&$count): void {
                foreach ($transactions as $transaction) {
                    $this->stripeService->settleTransaction($transaction);
                    $count++;
                }
            });

        $this->info("stripe:settle-balances — {$count} transação(ões) liquidada(s).");

        return self::SUCCESS;
    }
}

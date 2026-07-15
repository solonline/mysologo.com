<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Commands;

use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Models\StripePaymentIntent;
use App\Models\StripeTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePendingToPaymentIntents extends Command
{
    protected $signature = 'stripe:migrate-pending-to-payment-intents {--dry-run : Apenas mostra o que seria migrado}';

    protected $description = 'Move stripe_transactions pendentes (sem pagamento) para stripe_payment_intents e remove-as das transações financeiras.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pending = StripeTransaction::query()
            ->where('status', 'pending')
            ->where('net_amount', 0)
            ->whereNotNull('stripe_payment_intent_id')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nenhuma transação pendente legacy encontrada.');

            return self::SUCCESS;
        }

        $this->info("Encontradas {$pending->count()} transação(ões) pendente(s) legacy.");

        $migrated = 0;
        $skipped = 0;

        foreach ($pending as $transaction) {
            if (StripePaymentIntent::withTrashed()->where('stripe_payment_intent_id', $transaction->stripe_payment_intent_id)->exists()) {
                $skipped++;
                if (! $dryRun) {
                    $transaction->delete();
                }

                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] migrar PI {$transaction->stripe_payment_intent_id} (tx #{$transaction->id})");
                $migrated++;

                continue;
            }

            DB::transaction(function () use ($transaction): void {
                StripePaymentIntent::create([
                    'customer_id' => $transaction->customer_id,
                    'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
                    'stripe_payment_method_fee_id' => $transaction->stripe_payment_method_fee_id,
                    'tpa_id' => $transaction->tpa_id,
                    'type' => $transaction->getRawOriginal('type'),
                    'purpose' => $this->resolvePurpose($transaction),
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'payment_method_type' => $transaction->payment_method_type,
                    'status' => StripePaymentIntentStatus::RequiresPaymentMethod,
                    'description' => $transaction->description,
                    'stripe_metadata' => $transaction->stripe_metadata,
                    'stripe_cash_balance_transaction_id' => $transaction->stripe_cash_balance_transaction_id,
                    'stripe_created_at' => $transaction->stripe_created_at,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ]);

                $transaction->delete();
            });

            $migrated++;
        }

        $this->info("Concluído — migradas: {$migrated}, ignoradas (PI já existia): {$skipped}".($dryRun ? ' [dry-run]' : ''));

        return self::SUCCESS;
    }

    private function resolvePurpose(StripeTransaction $transaction): string
    {
        if ($transaction->stripe_cash_balance_transaction_id) {
            return StripePaymentIntentPurpose::BankTransferTopup->value;
        }

        if ($transaction->payment_method_type === 'card_present') {
            return StripePaymentIntentPurpose::Terminal->value;
        }

        return StripePaymentIntentPurpose::Topup->value;
    }
}

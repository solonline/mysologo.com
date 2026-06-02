<?php

namespace App\Services;

use App\Enums\CustomerPayoutStatus;
use App\Enums\LedgerEntryType;
use App\Enums\SettlementBatchStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\CustomerPayout;
use App\Models\CustomerWallesterAccount;
use App\Models\SettlementBatch;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    /**
     * Ponto de entrada do pipeline diário.
     * Agrupa transações elegíveis por moeda, cria batches e executa o settlement.
     */
    public function runDailySettlement(Carbon $date): array
    {
        $eligible = Transaction::query()
            ->where('status', 'succeeded')
            ->where('direction', TransactionDirection::Credit->value)
            ->whereNotNull('available_on')
            ->whereDate('available_on', '<=', $date)
            ->whereNull('balance_settled_at')
            ->whereNotIn('type', [TransactionType::P2P->value])
            ->get();

        if ($eligible->isEmpty()) {
            return ['batches' => 0, 'transactions' => 0];
        }

        $batchCount = 0;

        // Agrupa por moeda para criar um batch por moeda por dia
        foreach ($eligible->groupBy('currency') as $currency => $transactions) {
            $batch = $this->createBatch($date, $currency, $transactions);
            $this->processBatch($batch, $transactions);
            $batchCount++;
        }

        return ['batches' => $batchCount, 'transactions' => $eligible->count()];
    }

    /**
     * Cria (ou recupera) o settlement batch para a data e moeda.
     */
    private function createBatch(Carbon $date, string $currency, $transactions): SettlementBatch
    {
        return SettlementBatch::firstOrCreate(
            ['currency' => strtoupper($currency), 'available_date' => $date->toDateString()],
            [
                'total_amount'      => $transactions->sum('net_amount'),
                'transaction_count' => $transactions->count(),
                'status'            => SettlementBatchStatus::PENDING,
            ],
        );
    }

    /**
     * Executa o pipeline completo de um batch:
     * 1. Marca transações como settled (move pending → available)
     * 2. Envia agregado para conta master
     * 3. Distribui para IBANs individuais
     */
    public function processBatch(SettlementBatch $batch, $transactions = null): void
    {
        if ($batch->isCompleted()) {
            return;
        }

        $transactions ??= Transaction::query()
            ->where('status', 'succeeded')
            ->where('direction', TransactionDirection::Credit->value)
            ->where('currency', $batch->currency)
            ->whereDate('available_on', $batch->available_date)
            ->whereNull('balance_settled_at')
            ->get();

        try {
            // Etapa 1 — settle cada transação e registar no ledger
            DB::transaction(function () use ($transactions, $batch) {
                foreach ($transactions as $tx) {
                    $this->settleTransaction($tx, $batch);
                }
            });

            // Etapa 2 — transferência para conta master (integração externa)
            $this->transferToMaster($batch);

            // Etapa 3 — distribuição para IBANs individuais
            $batch->update(['status' => SettlementBatchStatus::DISTRIBUTING]);
            $this->distributeToCustomers($batch);

            $batch->update([
                'status'       => SettlementBatchStatus::COMPLETED,
                'completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $batch->update([
                'status'         => SettlementBatchStatus::FAILED,
                'failed_at'      => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            Log::channel('stripe')->error('SettlementService: batch falhou', [
                'batch_id' => $batch->id,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Etapa 1: settle uma transação individual e registar no ledger.
     */
    private function settleTransaction(Transaction $tx, SettlementBatch $batch): void
    {
        if ($tx->balance_settled_at !== null) {
            return;
        }

        if ($tx->net_amount > 0) {
            $this->ledgerService->record(
                customerId:  $tx->customer_id,
                currency:    $tx->currency,
                entryType:   LedgerEntryType::PAYMENT_RECEIVED,
                amount:      $tx->net_amount,
                reference:   $tx,
                description: "Settlement batch #{$batch->id}",
            );
        }

        $tx->update(['balance_settled_at' => now()]);
    }

    /**
     * Etapa 2: envia o total do batch para a conta master via terceiro.
     * Substituir pelo cliente real da integração quando disponível.
     */
    private function transferToMaster(SettlementBatch $batch): void
    {
        if ($batch->master_transfer_id !== null) {
            return; // já transferido (idempotente)
        }

        // TODO: substituir pela chamada real ao cliente do terceiro
        // $result = $this->thirdPartyClient->createTransfer([
        //     'amount'   => $batch->total_amount,
        //     'currency' => $batch->currency,
        //     'reference'=> "BATCH-{$batch->id}",
        // ]);
        // $transferId = $result['id'];

        $transferId = 'PENDING_INTEGRATION'; // placeholder

        $batch->update([
            'master_transfer_id'    => $transferId,
            'master_transferred_at' => now(),
            'status'                => SettlementBatchStatus::TRANSFERRED,
        ]);

        Log::channel('stripe')->info('SettlementService: transferência master registada', [
            'batch_id'    => $batch->id,
            'transfer_id' => $transferId,
            'amount'      => $batch->total_amount,
            'currency'    => $batch->currency,
        ]);
    }

    /**
     * Etapa 3: cria um CustomerPayout por customer e envia para o IBAN individual.
     */
    private function distributeToCustomers(SettlementBatch $batch): void
    {
        // Agrega net_amount por customer para este batch
        $byCustomer = Transaction::query()
            ->where('status', 'succeeded')
            ->where('direction', TransactionDirection::Credit->value)
            ->where('currency', $batch->currency)
            ->whereDate('available_on', $batch->available_date)
            ->whereNotNull('balance_settled_at')
            ->selectRaw('customer_id, SUM(net_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        foreach ($byCustomer as $customerId => $amount) {
            $this->processCustomerPayout($batch, $customerId, (int) $amount);
        }
    }

    private function processCustomerPayout(SettlementBatch $batch, int $customerId, int $amount): void
    {
        $payout = CustomerPayout::firstOrCreate(
            ['settlement_batch_id' => $batch->id, 'customer_id' => $customerId],
            ['amount' => $amount, 'currency' => $batch->currency, 'status' => CustomerPayoutStatus::PENDING],
        );

        if ($payout->status !== CustomerPayoutStatus::PENDING) {
            return;
        }

        $ibanAccount = CustomerWallesterAccount::where('customer_id', $customerId)->first();

        if (! $ibanAccount?->iban) {
            $payout->update([
                'status'         => CustomerPayoutStatus::SKIPPED,
                'failure_reason' => 'Sem IBAN configurado — saldo mantido na plataforma.',
            ]);

            Log::channel('stripe')->warning('SettlementService: payout ignorado (sem IBAN)', [
                'customer_id' => $customerId,
                'batch_id'    => $batch->id,
            ]);

            return;
        }

        $payout->update([
            'status'              => CustomerPayoutStatus::PROCESSING,
            'iban'                => $ibanAccount->iban,
            'bic'                 => $ibanAccount->swift_code,
            'account_holder_name' => $ibanAccount->receiver_name,
        ]);

        try {
            // TODO: substituir pela chamada real ao cliente do terceiro
            // $result = $this->thirdPartyClient->createPayout([
            //     'amount'     => $amount,
            //     'currency'   => $batch->currency,
            //     'iban'       => $ibanAccount->iban,
            //     'bic'        => $ibanAccount->swift_code,
            //     'name'       => $ibanAccount->receiver_name,
            //     'reference'  => "PAYOUT-{$payout->id}",
            // ]);
            // $externalId = $result['id'];

            $externalId = 'PENDING_INTEGRATION'; // placeholder

            DB::transaction(function () use ($payout, $externalId, $customerId, $batch, $amount) {
                $payout->update([
                    'status'               => CustomerPayoutStatus::COMPLETED,
                    'external_transfer_id' => $externalId,
                    'processed_at'         => now(),
                ]);

                $this->ledgerService->record(
                    customerId:  $customerId,
                    currency:    $batch->currency,
                    entryType:   LedgerEntryType::SETTLEMENT,
                    amount:      $amount,
                    reference:   $payout,
                    description: "IBAN payout batch #{$batch->id}",
                );
            });

        } catch (\Throwable $e) {
            $payout->update([
                'status'         => CustomerPayoutStatus::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            Log::channel('stripe')->error('SettlementService: payout falhou', [
                'payout_id'   => $payout->id,
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}

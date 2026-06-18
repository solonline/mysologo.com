<?php

namespace App\Domains\Ebury\Jobs;

use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SimulateEburyDecisionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $customerId,
        protected string $decision,
    ) {}

    public function handle(IntegrationAccountService $integrationAccounts): void
    {
        if (! config('services.ebury.simulate', true)) {
            return;
        }

        $customer = Customer::with('eburyAccount')->find($this->customerId);

        if (! $customer?->eburyAccount) {
            Log::warning('Ebury simulation: account not found', ['customer_id' => $this->customerId]);

            return;
        }

        [$status, $reason] = match (strtolower($this->decision)) {
            'approved', 'active' => ['active', 'Conta aprovada pela Ebury (simulação).'],
            'rejected', 'declined' => ['rejected', 'Conta recusada pela Ebury (simulação).'],
            default => ['in_review', 'Análise em curso na Ebury (simulação).'],
        };

        $integrationAccounts->applyEburyExternalStatus(
            $customer->eburyAccount,
            $status,
            $reason,
            'ebury.simulator',
        );

        Log::info('Ebury simulation: decision applied', [
            'customer_id' => $this->customerId,
            'decision' => $this->decision,
            'status' => $status,
        ]);
    }
}

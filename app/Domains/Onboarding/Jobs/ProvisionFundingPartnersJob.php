<?php

namespace App\Domains\Onboarding\Jobs;

use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Onboarding\Services\FinancialProvisioningService;
use App\Domains\Onboarding\Services\ProvisioningFailureNotifier;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionFundingPartnersJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(public int $customerId)
    {
        $this->tries = (int) config('services.provisioning.tries', 3);
        $this->backoff = config('services.provisioning.backoff', [60, 300, 900]);
    }

    public function handle(
        FinancialProvisioningService $provisioning,
        CustomerLifecycleService $lifecycle,
    ): void {
        $customer = Customer::query()->find($this->customerId);

        if (! $customer) {
            Log::warning('ProvisionFundingPartnersJob: customer not found', [
                'customer_id' => $this->customerId,
            ]);

            return;
        }

        $provisioning->provisionPostKycPartners($customer);
        $lifecycle->evaluateActivation($customer->fresh());
    }

    public function failed(Throwable $exception): void
    {
        $customer = Customer::query()->find($this->customerId);

        if (! $customer) {
            return;
        }

        app(FinancialProvisioningService::class)->handleProvisioningExhausted(
            $customer,
            $exception->getMessage(),
        );

        app(ProvisioningFailureNotifier::class)->notifyWallesterFailure(
            $customer->fresh(),
            $exception->getMessage(),
        );
    }
}

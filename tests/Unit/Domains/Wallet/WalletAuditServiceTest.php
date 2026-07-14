<?php

namespace Tests\Unit\Domains\Wallet;

use App\Domains\Wallet\Enums\TransactionType;
use App\Domains\Wallet\Services\Audit\WalletAuditService;
use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class WalletAuditServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_audit_reports_platform_revenue_ledger_mismatch(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $report = app(WalletAuditService::class)->run();

        $codes = array_map(fn ($finding) => $finding->code, $report->findings);

        $this->assertContains('PLATFORM_REVENUE_LEDGER_MISMATCH', $codes);
    }

    public function test_audit_skips_platform_revenue_check_when_customer_filter_is_set(): void
    {
        $this->createPlatformCustomer();
        $retail = $this->createRetailCustomer();

        StripeTransaction::query()->create([
            'customer_id' => $retail->id,
            'type' => TransactionType::TOPUP,
            'payment_method_type' => 'card',
            'direction' => 'credit',
            'amount' => 1_000,
            'stripe_fee' => 280,
            'platform_fee' => 10,
            'net_amount' => 710,
            'currency' => 'EUR',
            'status' => 'succeeded',
        ]);

        $report = app(WalletAuditService::class)->run(customerId: $retail->id);

        $codes = array_map(fn ($finding) => $finding->code, $report->findings);

        $this->assertNotContains('PLATFORM_REVENUE_LEDGER_MISMATCH', $codes);
    }

    private function createPlatformCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Yoop Platform',
            'email' => 'platform-audit-test@yoop.com',
            'username' => 'platform_audit_test',
            'type' => 'platform',
            'currency' => 'EUR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);
    }
}

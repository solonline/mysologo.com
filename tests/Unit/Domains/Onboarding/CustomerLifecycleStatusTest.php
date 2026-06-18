<?php

namespace Tests\Unit\Domains\Onboarding;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use PHPUnit\Framework\TestCase;

class CustomerLifecycleStatusTest extends TestCase
{
    public function test_only_active_can_transact(): void
    {
        $this->assertTrue(CustomerLifecycleStatus::ACTIVE->canTransact());
        $this->assertFalse(CustomerLifecycleStatus::PROVISIONING->canTransact());
        $this->assertFalse(CustomerLifecycleStatus::BLOCKED->canTransact());
    }

    public function test_negative_statuses_exclude_ebury_rejected(): void
    {
        $negative = CustomerLifecycleStatus::negativeStatuses();

        $this->assertContains(CustomerLifecycleStatus::IDENTITY_REJECTED, $negative);
        $this->assertContains(CustomerLifecycleStatus::BLOCKED, $negative);
        $this->assertNotContains(CustomerLifecycleStatus::EBURY_REJECTED, $negative);
    }

    public function test_legacy_status_mapping(): void
    {
        $this->assertSame(
            CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
            CustomerLifecycleStatus::fromLegacy('pending'),
        );
        $this->assertSame(
            CustomerLifecycleStatus::ACTIVE,
            CustomerLifecycleStatus::fromLegacy('active'),
        );
    }
}

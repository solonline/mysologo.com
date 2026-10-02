<?php

namespace App\Domains\Reward\Listeners;

use App\Domains\Onboarding\Events\CustomerApproved;
use App\Domains\Reward\Services\ReferralRewardService;

class RecordReferralOnCustomerApproved
{
    public function __construct(
        protected ReferralRewardService $rewards,
    ) {}

    public function handle(CustomerApproved $event): void
    {
        $this->rewards->recordFromApprovedCustomer($event->customer);
    }
}

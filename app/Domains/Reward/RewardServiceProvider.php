<?php

namespace App\Domains\Reward;

use App\Domains\Onboarding\Events\CustomerApproved;
use App\Domains\Reward\Commands\BackfillReferralRewardsCommand;
use App\Domains\Reward\Contracts\RewardPolicyInterface;
use App\Domains\Reward\Listeners\RecordReferralOnCustomerApproved;
use App\Domains\Reward\Policies\ActiveCustomerReferralPolicy;
use App\Domains\Reward\Services\ReferralRewardService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RewardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RewardPolicyInterface::class, ActiveCustomerReferralPolicy::class);
        $this->app->singleton(ReferralRewardService::class);
    }

    public function boot(): void
    {
        Event::listen(CustomerApproved::class, RecordReferralOnCustomerApproved::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillReferralRewardsCommand::class,
            ]);
        }

        Route::middleware('api')
            ->prefix('api/reward')
            ->group(__DIR__.'/Routes/reward.php');
    }
}

<?php

namespace App\Domains\Reward\Commands;

use App\Domains\Reward\Services\ReferralRewardService;
use Illuminate\Console\Command;

class BackfillReferralRewardsCommand extends Command
{
    protected $signature = 'reward:backfill-referrals';

    protected $description = 'Cria rewards de indicação para customers que já têm referred_by_customer_id.';

    public function handle(ReferralRewardService $rewards): int
    {
        $result = $rewards->backfillFromExistingReferrals();

        $this->info('reward:backfill-referrals');
        $this->table(
            ['Criados', 'Já existiam', 'Ignorados'],
            [[$result['created'], $result['existing'], $result['ignored']]],
        );

        return self::SUCCESS;
    }
}

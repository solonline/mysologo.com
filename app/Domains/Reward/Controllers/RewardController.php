<?php

namespace App\Domains\Reward\Controllers;

use App\Domains\Reward\Services\ReferralRewardService;
use App\Http\Resources\RewardResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class RewardController extends Controller
{
    public function __construct(
        protected ReferralRewardService $rewardService,
    ) {}

    /**
     * List My Rewards
     *
     * Returns rewards earned by the authenticated customer, of any program.
     *
     * @tags Reward
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rewards = $this->rewardService->listForCustomer($request->user());

        return RewardResource::collection($rewards);
    }
}

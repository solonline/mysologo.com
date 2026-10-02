<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CreateRewardProgramAction;
use App\Domains\Backoffice\Actions\DeleteRewardProgramAction;
use App\Domains\Backoffice\Actions\UpdateRewardProgramAction;
use App\Domains\Backoffice\DTOs\RewardProgramData;
use App\Domains\Backoffice\Queries\ListRewardProgramRewardsQuery;
use App\Domains\Backoffice\Queries\ListRewardProgramsQuery;
use App\Domains\Backoffice\Requests\StoreRewardProgramRequest;
use App\Domains\Backoffice\Requests\UpdateRewardProgramRequest;
use App\Domains\Reward\Enums\RewardStatus;
use App\Http\Controllers\Controller;
use App\Models\RewardProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RewardProgramController extends Controller
{
    public function __construct(
        protected ListRewardProgramsQuery $listPrograms,
        protected ListRewardProgramRewardsQuery $listRewards,
        protected CreateRewardProgramAction $createProgram,
        protected UpdateRewardProgramAction $updateProgram,
        protected DeleteRewardProgramAction $deleteProgram,
    ) {}

    public function index(Request $request)
    {
        $result = $this->listPrograms->execute($request->only(['search', 'status']));

        return view('dashboard.reward-programs.index', [
            'items' => $result->items->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('dashboard.reward-programs.create');
    }

    public function store(StoreRewardProgramRequest $request): RedirectResponse
    {
        $program = $this->createProgram->execute(RewardProgramData::fromRequest($request));

        return redirect()
            ->route('dashboard.reward-programs.show', $program)
            ->with('success', 'Programa criado com sucesso.');
    }

    public function show(Request $request, RewardProgram $rewardProgram)
    {
        $rewards = $this->listRewards
            ->execute($rewardProgram, $request->only(['search', 'status']))
            ->withQueryString();

        return view('dashboard.reward-programs.show', [
            'program' => $rewardProgram->loadCount('rewards'),
            'rewards' => $rewards,
            'statuses' => RewardStatus::cases(),
        ]);
    }

    public function edit(RewardProgram $rewardProgram)
    {
        return view('dashboard.reward-programs.edit', [
            'program' => $rewardProgram,
        ]);
    }

    public function update(UpdateRewardProgramRequest $request, RewardProgram $rewardProgram): RedirectResponse
    {
        $this->updateProgram->execute($rewardProgram, RewardProgramData::fromRequest($request));

        return redirect()
            ->route('dashboard.reward-programs.show', $rewardProgram)
            ->with('success', 'Programa actualizado com sucesso.');
    }

    public function destroy(RewardProgram $rewardProgram): RedirectResponse
    {
        try {
            $this->deleteProgram->execute($rewardProgram);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('dashboard.reward-programs.show', $rewardProgram)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('dashboard.reward-programs')
            ->with('success', 'Programa removido com sucesso.');
    }
}

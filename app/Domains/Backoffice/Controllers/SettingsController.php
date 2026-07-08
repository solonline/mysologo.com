<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetWallesterFundingOverviewQuery;
use App\Domains\Backoffice\Requests\UpdateSettingsRequest;
use App\Domains\Backoffice\Services\SettingsManagementService;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsManagementService $settings,
        protected GetWallesterFundingOverviewQuery $wallesterFundingOverviewQuery,
    ) {}

    public function index()
    {
        $wallesterFunding = $this->wallesterFundingOverviewQuery->execute();

        return view('dashboard.settings.index', [
            'settings' => $this->settings->allKeyedByKey(),
            'wallesterMainAccounts' => $wallesterFunding['mainAccounts'],
            'wallesterTopupFundingCounts' => $wallesterFunding['topupFundingCounts'],
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settings->updateMany($request->validated('settings'));

        return redirect()->route('dashboard.settings')
            ->with('success', 'Configurações guardadas com sucesso.');
    }
}

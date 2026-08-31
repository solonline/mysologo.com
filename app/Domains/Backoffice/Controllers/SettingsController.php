<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetWallesterFundingOverviewQuery;
use App\Domains\Backoffice\Requests\TestMailSettingsRequest;
use App\Domains\Backoffice\Requests\UpdateSettingsRequest;
use App\Domains\Backoffice\Services\SettingsManagementService;
use App\Domains\Settings\Services\MailSettingsService;
use App\Http\Controllers\Controller;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsManagementService $settings,
        protected GetWallesterFundingOverviewQuery $wallesterFundingOverviewQuery,
        protected MailSettingsService $mailSettings,
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

    public function testMail(TestMailSettingsRequest $request)
    {
        $to = $request->validated('to');

        try {
            $this->mailSettings->sendTest($to);
        } catch (Throwable $e) {
            return redirect()->route('dashboard.settings')
                ->with('settings_tab', 'mail')
                ->with('error', 'Falha ao enviar email de teste: '.$e->getMessage());
        }

        return redirect()->route('dashboard.settings')
            ->with('settings_tab', 'mail')
            ->with('success', "Email de teste enviado para {$to}.");
    }
}

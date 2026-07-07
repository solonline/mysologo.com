<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Requests\UpdateSettingsRequest;
use App\Domains\Backoffice\Services\SettingsManagementService;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\CustomerWallesterAccount;
use App\Models\Transaction;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsManagementService $settings,
    ) {}

    public function index()
    {
        return view('dashboard.settings.index', [
            'settings' => $this->settings->allKeyedByKey(),
            'wallesterMainAccounts' => CustomerWallesterAccount::query()
                ->where('is_main', true)
                ->orderBy('funding_currency')
                ->get(['id', 'funding_currency', 'currency_code', 'wallester_account_id', 'available_amount', 'balance']),
            'wallesterTopupFundingCounts' => [
                'awaiting_liquidity' => Transaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::AwaitingLiquidity->value)
                    ->count(),
                'failed' => Transaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::Failed->value)
                    ->count(),
                'pending' => Transaction::query()
                    ->where('type', TransactionType::TOPUP->value)
                    ->where('status', 'succeeded')
                    ->where('partner_funding_status', PartnerFundingStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settings->updateMany($request->validated('settings'));

        return redirect()->route('dashboard.settings')
            ->with('success', 'Configurações guardadas com sucesso.');
    }
}

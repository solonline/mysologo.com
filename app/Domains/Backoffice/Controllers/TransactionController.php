<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\ListFundingPartnerMovementsQuery;
use App\Domains\Backoffice\Queries\ListStripeTransactionsQuery;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TransactionType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        protected ListStripeTransactionsQuery $listStripeTransactions,
        protected ListFundingPartnerMovementsQuery $listFundingPartnerMovements,
    ) {}

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['stripe', 'wallester'], true)
            ? $request->query('tab')
            : 'stripe';

        $perPage = min((int) $request->input('per_page', 25), 100);

        $stripeTransactions = null;
        $movements = null;

        if ($tab === 'wallester') {
            $movements = $this->listFundingPartnerMovements->execute(
                $request->only(['search', 'movement_type', 'movement_status', 'direction', 'source', 'from', 'to']),
                $perPage,
            )->withQueryString();
        } else {
            $stripeTransactions = $this->listStripeTransactions->execute(
                $request->only(['search', 'type', 'status', 'partner_funding_status', 'from', 'to', 'with_platform_fee']),
                $perPage,
            )->withQueryString();
        }

        return view('dashboard.transactions.index', [
            'tab' => $tab,
            'stripeTransactions' => $stripeTransactions,
            'movements' => $movements,
            'transactionTypes' => [
                TransactionType::TOPUP,
                TransactionType::P2P,
                TransactionType::CARD,
                TransactionType::BANK_TRANSFER,
            ],
            'partnerFundingStatuses' => PartnerFundingStatus::cases(),
            'movementTypes' => FundingPartnerMovementType::cases(),
            'movementStatuses' => FundingPartnerMovementStatus::cases(),
            'movementDirections' => FundingPartnerMovementDirection::cases(),
            'movementSources' => FundingPartnerMovementSource::cases(),
        ]);
    }
}

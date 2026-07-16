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
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        protected ListStripeTransactionsQuery $listStripeTransactions,
        protected ListFundingPartnerMovementsQuery $listFundingPartnerMovements,
    ) {}

    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function customer(Request $request, Customer $customer): View
    {
        return $this->renderIndex($request, $customer);
    }

    private function renderIndex(Request $request, ?Customer $customer = null): View
    {
        $tab = in_array($request->query('tab'), ['stripe', 'wallester'], true)
            ? $request->query('tab')
            : 'stripe';

        $perPage = min((int) $request->input('per_page', 25), 100);

        $stripeTransactions = null;
        $movements = null;

        if ($tab === 'wallester') {
            $filters = $request->only(['search', 'movement_type', 'movement_status', 'direction', 'source', 'from', 'to']);
            $filters['customer_id'] = $customer?->id;

            $movements = $this->listFundingPartnerMovements->execute(
                $filters,
                $perPage,
            )->withQueryString();
        } else {
            $filters = $request->only(['search', 'type', 'status', 'partner_funding_status', 'from', 'to', 'with_platform_fee']);
            $filters['customer_id'] = $customer?->id;

            $stripeTransactions = $this->listStripeTransactions->execute(
                $filters,
                $perPage,
            )->withQueryString();
        }

        return view('dashboard.transactions.index', [
            'tab' => $tab,
            'customerContext' => $customer,
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

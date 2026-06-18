<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CreateStripeFeeAction;
use App\Domains\Backoffice\Actions\DeleteStripeFeeAction;
use App\Domains\Backoffice\Actions\UpdateStripeFeeAction;
use App\Domains\Backoffice\DTOs\StripeFeeData;
use App\Domains\Backoffice\Queries\ListStripeFeesQuery;
use App\Domains\Backoffice\Requests\StoreStripeFeeRequest;
use App\Domains\Backoffice\Requests\UpdateStripeFeeRequest;
use App\Http\Controllers\Controller;
use App\Models\StripePaymentMethodFee;
use Illuminate\Http\Request;

class StripeFeeController extends Controller
{
    public function __construct(
        protected ListStripeFeesQuery $listFees,
        protected CreateStripeFeeAction $createFee,
        protected UpdateStripeFeeAction $updateFee,
        protected DeleteStripeFeeAction $deleteFee,
    ) {}

    public function index(Request $request)
    {
        $result = $this->listFees->execute($request->only(['search', 'currency']));

        return view('dashboard.stripe-fees.index', [
            'fees' => $result->fees->withQueryString(),
            'currencies' => $result->currencies,
        ]);
    }

    public function create()
    {
        $form = $this->listFees->formData();

        return view('dashboard.stripe-fees.create', [
            'currencies' => $form->currencies,
            'stripeAvailability' => $form->stripeAvailability,
        ]);
    }

    public function store(StoreStripeFeeRequest $request)
    {
        $this->createFee->execute(
            StripeFeeData::fromValidated($request->validated(), $request->boolean('is_active')),
        );

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa criada com sucesso.');
    }

    public function edit(StripePaymentMethodFee $stripeFee)
    {
        $form = $this->listFees->editData($stripeFee);

        return view('dashboard.stripe-fees.edit', [
            'stripeFee' => $stripeFee,
            'currencies' => $form->currencies,
        ]);
    }

    public function update(UpdateStripeFeeRequest $request, StripePaymentMethodFee $stripeFee)
    {
        $this->updateFee->execute(
            $stripeFee,
            StripeFeeData::fromValidated($request->validated(), $request->boolean('is_active')),
        );

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa atualizada com sucesso.');
    }

    public function destroy(StripePaymentMethodFee $stripeFee)
    {
        $this->deleteFee->execute($stripeFee);

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa removida com sucesso.');
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Clients\StripeClient;
use App\Http\Controllers\Controller;
use App\Models\StripePaymentMethodFee;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StripeFeeController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly StripeClient $stripeClient,
    ) {
    }
    public function index(Request $request)
    {
        $query = StripePaymentMethodFee::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%");
            });
        }

        if ($currency = $request->input('currency')) {
            $query->where('currency', strtoupper($currency));
        }

        $currencies = $this->settingsService->getByKey('currency.accepted');
        $currencies = json_decode($currencies, true) ?: [];
        $fees = $query->orderBy('label')->paginate(20)->withQueryString();

        return view('dashboard.stripe-fees.index', compact('fees', 'currencies'));
    }

    public function create()
    {
        $currencies = json_decode($this->settingsService->getByKey('currency.accepted'), true) ?: [];

        try {
            $stripeAvailability = $this->stripeClient->getPaymentMethodAvailability();
        } catch (\Throwable) {
            $stripeAvailability = [];
        }

        return view('dashboard.stripe-fees.create', compact('currencies', 'stripeAvailability'));
    }

    public function store(Request $request)
    {
        $currencies = json_decode($this->settingsService->getByKey('currency.accepted'), true) ?: [];

        $data = $request->validate([
            'payment_method'          => [
                'required', 'string', 'max:100',
                Rule::unique('stripe_payment_method_fees')->where('currency', $request->input('currency')),
            ],
            'label'                   => ['required', 'string', 'max:150'],
            'stripe_fee_percentage'   => ['required', 'numeric', 'min:0', 'max:100'],
            'stripe_fee_fixed'        => ['required', 'numeric', 'min:0'],
            'platform_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'platform_fee_fixed'      => ['required', 'numeric', 'min:0'],
            'currency'                => ['required', 'string', 'size:3', Rule::in($currencies)],
            'is_active'               => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['stripe_fee_percentage']   = $data['stripe_fee_percentage'] / 100;
        $data['platform_fee_percentage'] = $data['platform_fee_percentage'] / 100;

        StripePaymentMethodFee::create($data);

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa criada com sucesso.');
    }

    public function edit(StripePaymentMethodFee $stripeFee)
    {
        $currencies = $this->settingsService->getByKey('currency.accepted');

        $currencies = json_decode($currencies, true) ?: [];
        return view('dashboard.stripe-fees.edit', compact('stripeFee', 'currencies'));
    }

    public function update(Request $request, StripePaymentMethodFee $stripeFee)
    {
        $paymentMethods = json_decode($this->settingsService->getByKey('payment_methods.enabled'), true) ?: [];
        $currencies     = json_decode($this->settingsService->getByKey('currency.accepted'), true) ?: [];

        $data = $request->validate([
            'payment_method'          => [
                'required', 'string', 'max:100',
                Rule::in($paymentMethods),
                Rule::unique('stripe_payment_method_fees')->where('currency', $request->input('currency'))->ignore($stripeFee->id),
            ],
            'label'                   => ['required', 'string', 'max:150'],
            'stripe_fee_percentage'   => ['required', 'numeric', 'min:0', 'max:1'],
            'stripe_fee_fixed'        => ['required', 'numeric', 'min:0'],
            'platform_fee_percentage' => ['required', 'numeric', 'min:0', 'max:1'],
            'platform_fee_fixed'      => ['required', 'numeric', 'min:0'],
            'currency'                => ['required', 'string', 'size:3', Rule::in($currencies)],
            'is_active'               => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $stripeFee->update($data);

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa atualizada com sucesso.');
    }

    public function destroy(StripePaymentMethodFee $stripeFee)
    {
        $stripeFee->delete();

        return redirect()->route('dashboard.stripe-fees')
            ->with('success', 'Taxa removida com sucesso.');
    }
}

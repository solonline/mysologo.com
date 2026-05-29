<?php

namespace App\Http\Controllers\Pay;

use App\Models\MerchantPaymentRequest;
use App\Models\StripePaymentMethodFee;
use App\Services\MerchantService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;

class PayController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService,
        protected SettingsService $settingsService,
    ) {}

    private const LINK_VISIBLE_DAYS = 7;

    public function show(Request $request, string $uuid)
    {
        $paymentRequest = MerchantPaymentRequest::where('uuid', $uuid)
            ->with(['merchant.customer', 'payer'])
            ->firstOrFail();

        if ($paymentRequest->created_at->addDays(self::LINK_VISIBLE_DAYS)->isPast()) {
            abort(404);
        }

        App::setLocale($paymentRequest->language ?? 'pt');

        if ($paymentRequest->isExpired() && ($paymentRequest->isPending() || $paymentRequest->isProcessing())) {
            $paymentRequest->update(['status' => 'expired']);
            $paymentRequest->refresh();
        }

        $customerCurrency = strtoupper($paymentRequest->merchant->customer->currency ?? 'EUR');

        $paymentMethodTypes = StripePaymentMethodFee::where('currency', $customerCurrency)
            ->where('is_active', true)
            ->get()
            ->map(fn(StripePaymentMethodFee $fee) => $fee->stripePaymentMethodType())
            ->unique()
            ->values()
            ->all();

        $clientSecret         = null;
        $awaitingConfirmation = false;
        $publishableKey       = $this->settingsService->getByKey("stripe.{$customerCurrency}.public_key");

        $redirectStatus = $request->query('redirect_status');

        if ($redirectStatus === 'succeeded' && $paymentRequest->isProcessing()) {
            // Utilizador regressou do redirect Stripe com sucesso mas o webhook ainda não chegou
            $awaitingConfirmation = true;
        } elseif ($paymentRequest->isPayable()) {
            $result = $this->merchantService->initializePayment($paymentRequest, $paymentMethodTypes);
            if ($result !== null) {
                $clientSecret = $result['client_secret'];
            }
            $paymentRequest->refresh();
        }

        $billingCountry = match(strtoupper($customerCurrency)) {
            'GBP' => 'GB',
            'USD' => 'US',
            'RON' => 'RO',
            default => 'PT',
        };

        return view('pay.show', compact('paymentRequest', 'clientSecret', 'publishableKey', 'billingCountry', 'awaitingConfirmation'));
    }
}

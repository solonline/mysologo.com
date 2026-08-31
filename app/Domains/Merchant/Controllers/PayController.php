<?php

namespace App\Domains\Merchant\Controllers;

use App\Domains\Merchant\Services\MerchantService;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Models\MerchantPaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;

class PayController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService,
        protected SettingsService $settingsService,
    ) {}

    public function show(Request $request, string $uuid)
    {
        $paymentRequest = MerchantPaymentRequest::where('uuid', $uuid)
            ->with(['merchant.customer', 'payer', 'stripePaymentIntent'])
            ->firstOrFail();

        App::setLocale($paymentRequest->language ?? 'pt');

        if ($paymentRequest->isExpired() && ($paymentRequest->isPending() || $paymentRequest->isProcessing())) {
            $paymentRequest->update(['status' => 'expired']);
            $paymentRequest->refresh();
        }

        $customerCurrency = strtoupper($paymentRequest->merchant->customer->currency ?? 'EUR');

        $clientSecret = null;
        $awaitingConfirmation = false;
        $publishableKey = $this->settingsService->getByKey("stripe.{$customerCurrency}.public_key");

        $redirectStatus = $request->query('redirect_status');
        $piStatus = $paymentRequest->stripePaymentIntent?->status;

        if ($redirectStatus === 'succeeded' && $paymentRequest->isProcessing()) {
            $awaitingConfirmation = true;
        } elseif ($piStatus === StripePaymentIntentStatus::Succeeded) {
            if ($paymentRequest->status !== 'completed') {
                $paymentRequest->update([
                    'status' => 'completed',
                    'paid_at' => $paymentRequest->paid_at ?? now(),
                ]);
                $paymentRequest->refresh();
            }
        } elseif ($piStatus === StripePaymentIntentStatus::Canceled) {
            if ($paymentRequest->status !== 'cancelled') {
                $paymentRequest->update(['status' => 'cancelled']);
                $paymentRequest->refresh();
            }
        } elseif ($piStatus === StripePaymentIntentStatus::Failed) {
            if ($paymentRequest->status !== 'failed') {
                $paymentRequest->update(['status' => 'failed']);
                $paymentRequest->refresh();
            }
        } elseif ($piStatus === StripePaymentIntentStatus::Processing
            || $piStatus === StripePaymentIntentStatus::RequiresCapture) {
            $awaitingConfirmation = true;
        } elseif ($paymentRequest->isPayable()) {
            $result = $this->merchantService->initializePayment($paymentRequest);
            if ($result !== null) {
                $clientSecret = $result['client_secret'];
            }
            $paymentRequest->refresh();
        }

        $billingCountry = match (strtoupper($customerCurrency)) {
            'GBP' => 'GB',
            'USD' => 'US',
            'RON' => 'RO',
            default => 'PT',
        };

        $isEmbed = $request->boolean('embed')
            || $request->headers->get('Sec-Fetch-Dest') === 'iframe';

        $returnUrl = null;
        $candidateReturnUrl = $request->query('return_url');
        if (is_string($candidateReturnUrl) && $candidateReturnUrl !== '') {
            $parts = parse_url($candidateReturnUrl);
            if (
                is_array($parts)
                && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                && ! empty($parts['host'])
            ) {
                $returnUrl = $candidateReturnUrl;
            }
        }

        return view('pay.show', compact(
            'paymentRequest',
            'clientSecret',
            'publishableKey',
            'billingCountry',
            'awaitingConfirmation',
            'isEmbed',
            'returnUrl',
        ));
    }
}

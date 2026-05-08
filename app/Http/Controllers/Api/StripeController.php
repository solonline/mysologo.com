<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Requests\Stripe\CreatePaymentIntentRequest;
use App\Http\Resources\TransactionResource;
use App\Models\StripePaymentMethodFee;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StripeController extends Controller
{
    public function __construct(protected StripeService $stripeService)
    {
    }

    public function paymentMethods(Request $request)
    {
        $methods = StripePaymentMethodFee::where('is_active', true)
            ->where('currency', strtoupper($request->query('currency', 'EUR')))
            ->get(['id', 'payment_method', 'label', 'stripe_fee_percentage', 'stripe_fee_fixed', 'platform_fee_percentage', 'platform_fee_fixed', 'currency']);

        return response()->json(['data' => $methods]);
    }

    public function create(CreatePaymentIntentRequest $request)
    {
        $customer = $request->user();
        $data = $request->validated();

        try {
            $result = $this->stripeService->createPaymentIntent(
                customer: $customer,
                amount: $data['amount'],
                paymentMethodFeeId: $data['payment_method_id'],
                currency: $data['currency'] ?? 'eur',
                description: $data['description'] ?? null,
            );
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'transaction'   => new TransactionResource($result['transaction']),
            'client_secret' => $result['client_secret'],
        ], 201);
    }

    public function createFunding(Request $request)
    {
        try {
            $funding = $this->stripeService->createVban($request->user());
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['data' => $funding], 201);
    }

    public function getFundingInstructions(Request $request)
    {
        try {
            $instructions = $this->stripeService->getFundingInstructions($request->user());
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['data' => $instructions]);
    }

}

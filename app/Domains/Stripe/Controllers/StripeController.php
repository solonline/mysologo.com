<?php

namespace App\Domains\Stripe\Controllers;

use App\Domains\Stripe\Services\StripeService;
use App\Exceptions\ApiException;
use App\Http\Requests\Stripe\CreatePaymentIntentRequest;
use App\Http\Requests\Stripe\CreateTerminalPaymentIntentRequest;
use App\Http\Resources\Stripe\FeeEstimateResource;
use App\Http\Resources\Stripe\PaymentMethodResource;
use App\Http\Resources\TransactionResource;
use App\Models\StripePaymentMethodFee;
use App\Models\StripeTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class StripeController extends Controller
{
    public function __construct(protected StripeService $stripeService) {}

    /**
     * List Payment Methods
     *
     * Returns the active payment methods available for a given currency, including their fee structure.
     *
     * @tags Stripe
     *
     * @queryParam currency string optional ISO 4217 currency code. Defaults to `EUR`. Example: EUR
     */
    public function paymentMethods(Request $request): AnonymousResourceCollection
    {
        $currency = strtoupper($request->query('currency', 'EUR'));

        $methods = Cache::remember("payment_methods:{$currency}", 300, fn () => StripePaymentMethodFee::where('is_active', true)
            ->where('currency', $currency)
            ->orderBy('id')
            ->get()
        );

        return PaymentMethodResource::collection($methods);
    }

    /**
     * Create Payment Intent
     *
     * Creates a Stripe PaymentIntent to top up the customer's balance. Returns a `client_secret` to confirm the payment on the client side using Stripe.js or a mobile SDK. Amounts are in the smallest currency unit (e.g. cents for EUR).
     *
     * @tags Stripe
     */
    public function create(CreatePaymentIntentRequest $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validated();

        try {
            $result = $this->stripeService->createPaymentIntent(
                customer: $customer,
                amount: $data['amount'],
                paymentMethodFeeId: $data['payment_method_id'],
                currency: $data['currency'] ?? 'eur',
                isTopup: true,
            );
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'transaction' => new TransactionResource($result['transaction']),
            'client_secret' => $result['client_secret'],
        ], 201);
    }

    /**
     * Create Terminal Payment Intent
     *
     * Creates a Stripe PaymentIntent for a card-present (Terminal) top-up. Returns a `client_secret` to be passed to the Stripe Terminal SDK reader. Amounts are in the smallest currency unit (e.g. cents for EUR).
     *
     * @tags Stripe, Terminal
     */
    public function terminalConnectionToken(Request $request): JsonResponse
    {
        $currency = strtolower($request->input('currency', 'eur'));

        try {
            $secret = $this->stripeService->createTerminalConnectionToken($currency);
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['secret' => $secret]);
    }

    public function createTerminal(CreateTerminalPaymentIntentRequest $request): JsonResponse
    {
        $customer = $request->attributes->get('merchant')->customer;
        $data = $request->validated();

        try {
            $result = $this->stripeService->createTerminalPaymentIntent(
                customer: $customer,
                amount: $data['amount'],
                currency: $data['currency'] ?? 'eur',
                tpaSerialNumber: $data['tpa_serial_number']
            );
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'transaction' => new TransactionResource($result['transaction']),
            'client_secret' => $result['client_secret'],
        ], 201);
    }

    /**
     * Capture Terminal Payment
     *
     * Captures a previously created Terminal PaymentIntent after the card has been read. The transaction must belong to the authenticated customer and be in a capturable state.
     *
     * @tags Stripe, Terminal
     */
    public function captureTerminal(Request $request, int $transactionId): JsonResponse
    {
        $customer = $request->user();

        $transaction = StripeTransaction::where('id', $transactionId)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $transaction) {
            return response()->json(['message' => 'Transação não encontrada.'], 404);
        }

        try {
            $this->stripeService->captureTerminalPayment($transaction);
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['message' => 'Captura solicitada com sucesso.']);
    }

    /**
     * Fee Estimates
     *
     * Returns the fee breakdown (platform + Stripe) for each active payment method. Useful for showing the total cost to the customer before initiating a payment.
     *
     * @tags Stripe
     *
     * @queryParam currency string optional ISO 4217 currency code. Defaults to `EUR`. Example: EUR
     */
    public function feeEstimates(Request $request): AnonymousResourceCollection
    {
        $currency = strtoupper($request->query('currency', 'EUR'));

        $fees = Cache::remember("fee_estimates:{$currency}", 300, fn () => StripePaymentMethodFee::where('is_active', true)
            ->where('currency', $currency)
            ->get()
        );

        return FeeEstimateResource::collection($fees)
            ->additional(['currency' => $currency]);
    }

    /**
     * Create Funding Instructions
     *
     * Creates or retries Stripe bank transfer funding instructions for the authenticated customer.
     *
     * @tags Stripe
     */
    public function createFunding(Request $request): JsonResponse
    {
        $customer = $request->user();

        try {
            $data = $this->stripeService->createCustomerFundingInstructions($customer);
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['data' => $data], 201);
    }

    /**
     * Get Funding Instructions
     *
     * Returns the persisted Stripe funding instructions for the authenticated customer.
     *
     * @tags Stripe
     */
    public function getFundingInstructions(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $this->stripeService->getFundingInstructions($customer);

        if ($data === null) {
            return response()->json(['message' => 'Cliente não possui conta Stripe associada.'], 404);
        }

        return response()->json(['data' => $data]);
    }
}

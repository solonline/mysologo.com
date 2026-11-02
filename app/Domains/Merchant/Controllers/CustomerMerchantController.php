<?php

namespace App\Domains\Merchant\Controllers;

use App\Domains\Merchant\Services\MerchantService;
use App\Http\Requests\Merchant\CreatePaymentRequestRequest;
use App\Http\Requests\Merchant\RegisterMerchantRequest;
use App\Http\Requests\Merchant\RotateMerchantCredentialsRequest;
use App\Http\Requests\Merchant\UpdateMerchantRequest;
use App\Http\Resources\MerchantPaymentRequestResource;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerMerchantController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MerchantResource::collection(
            $this->merchantService->listForCustomer($request->user()),
        );
    }

    public function paymentRequests(Request $request): AnonymousResourceCollection
    {
        $limit = min((int) $request->input('per_page', 50), 100);

        return MerchantPaymentRequestResource::collection(
            $this->merchantService->listPaymentRequestsForCustomer($request->user(), $limit),
        );
    }

    public function register(RegisterMerchantRequest $request): JsonResponse
    {
        $result = $this->merchantService->register($request->user(), $request->validated());

        return (new MerchantResource($result['merchant']))
            ->additional([
                'api_key' => $result['api_key'],
                'webhook_secret' => $result['webhook_secret'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMerchantRequest $request, int $merchant): JsonResponse
    {
        $owned = $this->resolveOwnedMerchant($request, $merchant);
        $updated = $this->merchantService->update($owned, $request->validated());

        return (new MerchantResource($updated))->response();
    }

    public function destroy(Request $request, int $merchant): JsonResponse
    {
        $owned = $this->resolveOwnedMerchant($request, $merchant);
        $this->merchantService->delete($owned);

        return response()->json(['ok' => true]);
    }

    public function credentials(Request $request, int $merchant): JsonResponse
    {
      
        $owned = $this->resolveOwnedMerchant($request, $merchant);
        $result = $this->merchantService->getCredentials($owned);


        return (new MerchantResource($result['merchant']))
            ->additional([
                'api_key_retrievable' => false,
                'webhook_secret' => $result['webhook_secret'],
            ])
            ->response();
    }

    public function rotateCredentials(
        RotateMerchantCredentialsRequest $request,
        int $merchant,
    ): JsonResponse {
        $owned = $this->resolveOwnedMerchant($request, $merchant);
        $result = $this->merchantService->rotateCredentials($owned, $request->validated());

        $additional = array_filter([
            'api_key' => $result['api_key'],
            'webhook_secret' => $result['webhook_secret'],
        ], fn ($value) => $value !== null);

        return (new MerchantResource($result['merchant']))
            ->additional($additional)
            ->response();
    }

    public function createPaymentRequest(
        CreatePaymentRequestRequest $request,
        int $merchant,
    ): JsonResponse {
        $owned = $this->resolveOwnedMerchant($request, $merchant);

        $paymentRequest = $this->merchantService->createPaymentRequest(
            $owned,
            $request->validated(),
        );

        return (new MerchantPaymentRequestResource($paymentRequest))
            ->response()
            ->setStatusCode(201);
    }

    private function resolveOwnedMerchant(Request $request, int $merchantId): Merchant
    {
        $owned = $this->merchantService->findOwnedByCustomer($request->user(), $merchantId);

        if (! $owned instanceof Merchant || ! $owned->is_active) {
            throw new NotFoundHttpException('Merchant not found.');
        }

        return $owned;
    }
}

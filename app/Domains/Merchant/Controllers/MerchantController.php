<?php

namespace App\Domains\Merchant\Controllers;

use App\Domains\Merchant\Services\MerchantService;
use App\Http\Requests\Merchant\CreatePaymentRequestRequest;
use App\Http\Resources\MerchantPaymentRequestResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MerchantController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService,
    ) {}

    public function createPaymentRequest(CreatePaymentRequestRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $paymentRequest = $this->merchantService->createPaymentRequest(
            $merchant,
            $request->validated(),
        );

        return (new MerchantPaymentRequestResource($paymentRequest))
            ->response()
            ->setStatusCode(201);
    }
}

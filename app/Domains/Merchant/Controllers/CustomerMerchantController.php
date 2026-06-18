<?php

namespace App\Domains\Merchant\Controllers;

use App\Domains\Merchant\Services\MerchantService;
use App\Http\Requests\Merchant\RegisterMerchantRequest;
use App\Http\Resources\MerchantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CustomerMerchantController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService,
    ) {}

    public function register(RegisterMerchantRequest $request): JsonResponse
    {
        $result = $this->merchantService->register($request->user(), $request->validated());

        return (new MerchantResource($result['merchant']))
            ->additional(['api_key' => $result['api_key']])
            ->response()
            ->setStatusCode(201);
    }
}

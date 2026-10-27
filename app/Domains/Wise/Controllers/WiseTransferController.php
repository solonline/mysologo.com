<?php

namespace App\Domains\Wise\Controllers;

use App\Domains\Wise\Http\Requests\CreateWiseTransferRequest;
use App\Domains\Wise\Services\WiseTransferService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class WiseTransferController extends Controller
{
    public function __construct(
        protected WiseTransferService $transfers,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function store(CreateWiseTransferRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'data' => $this->transfers->create($customer, $request->validated()),
        ], 201);
    }
}

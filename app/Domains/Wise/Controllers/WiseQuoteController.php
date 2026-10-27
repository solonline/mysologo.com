<?php

namespace App\Domains\Wise\Controllers;

use App\Domains\Wise\Http\Requests\CreateWiseQuoteRequest;
use App\Domains\Wise\Services\WiseTransferService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class WiseQuoteController extends Controller
{
    public function __construct(
        protected WiseTransferService $transfers,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function store(CreateWiseQuoteRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'data' => $this->transfers->quote($customer, $request->validated()),
        ]);
    }
}

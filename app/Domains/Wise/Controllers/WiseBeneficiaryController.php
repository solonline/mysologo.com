<?php

namespace App\Domains\Wise\Controllers;

use App\Domains\Wise\Http\Requests\CreateWiseBeneficiaryRequest;
use App\Domains\Wise\Services\WiseBeneficiaryService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WiseBeneficiaryController extends Controller
{
    public function __construct(
        protected WiseBeneficiaryService $beneficiaries,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'data' => $this->beneficiaries->listFor($customer),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function store(CreateWiseBeneficiaryRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $result = $this->beneficiaries->create($customer, $request->validated());

        return response()->json([
            'data' => $result['beneficiary']->toApiArray(),
        ], $result['created'] ? 201 : 200);
    }
}

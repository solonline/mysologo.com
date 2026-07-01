<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Wallester\Http\Requests\ListWallesterTransactionsRequest;
use App\Domains\Wallester\Services\WallesterTransactionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallesterTransactionController extends Controller
{
    public function __construct(
        protected WallesterTransactionService $transactionService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(ListWallesterTransactionsRequest $request): JsonResponse
    {
        $result = $this->transactionService->listForCustomer(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => $result['transactions'],
            'meta' => [
                'total_records_number' => $result['total_records_number'],
                'from_record' => $result['from_record'],
                'records_count' => $result['records_count'],
            ],
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function show(Request $request, string $transaction): JsonResponse
    {
        return response()->json([
            'data' => $this->transactionService->showForCustomer($request->user(), $transaction),
        ]);
    }
}

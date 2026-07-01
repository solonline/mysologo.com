<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Wallester\Http\Requests\ListWallesterAccountsRequest;
use App\Domains\Wallester\Services\WallesterAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallesterAccountController extends Controller
{
    public function __construct(
        protected WallesterAccountService $accountService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(ListWallesterAccountsRequest $request): JsonResponse
    {
        $result = $this->accountService->listForCustomer(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => $result['accounts'],
            'meta' => [
                'total_records_number' => $result['total_records_number'],
            ],
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function show(Request $request, string $account): JsonResponse
    {
        return response()->json([
            'data' => $this->accountService->showForCustomer($request->user(), $account),
        ]);
    }
}

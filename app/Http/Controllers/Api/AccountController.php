<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(protected AccountService $accountService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $details = $this->accountService->getDetails($request->user());

        return response()->json($details);
    }

    public function balance(Request $request): JsonResponse
    {
        $balances = $this->accountService->getBalances($request->user(), $request->query('currency'));

        return response()->json($balances);
    }
}

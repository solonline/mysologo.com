<?php

namespace App\Domains\Wise\Controllers;

use App\Domains\Wise\Contracts\WiseGatewayInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class WiseBalanceController extends Controller
{
    public function __construct(
        protected WiseGatewayInterface $gateway,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->gateway->listBalances(),
        ]);
    }
}

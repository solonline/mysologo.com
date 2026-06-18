<?php

namespace App\Domains\Tpa\Controllers;

use App\Domains\Tpa\Services\TpaLinkService;
use App\Http\Requests\Tpa\LinkTpaRequest;
use App\Http\Resources\Auth\TpaAuthResource;
use Illuminate\Routing\Controller;

class TpaController extends Controller
{
    public function __construct(
        private TpaLinkService $tpaLink,
    ) {}

    /**
     * Link TPA Device
     *
     * Links a TPA terminal to a customer account. After linking, the terminal can authenticate customers via `POST /login-tpa`.
     *
     * @tags TPA
     * @unauthenticated
     */
    public function link(LinkTpaRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $result = $this->tpaLink->link(
            email: $request->email,
            password: $request->password,
            tpaSerial: $request->tpa_serial,
        );

        return TpaAuthResource::make([
            'token' => $result['token'],
            'context' => 'tpa',
            'user' => $result['customer'],
            'tpa' => $result['tpa'],
        ])->response()->setStatusCode(201);
    }
}

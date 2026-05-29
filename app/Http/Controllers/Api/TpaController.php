<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CustomerRepositoryInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tpa\LinkTpaRequest;
use App\Http\Resources\Auth\TpaAuthResource;
use Illuminate\Support\Facades\Hash;

class TpaController extends Controller
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
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
        $customer = $this->customerRepository->findByEmail($request->email);

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw new ApiException(ErrorCode::INVALID_CREDENTIALS, 401, 'Invalid credentials.');
        }

        if ($customer->tpas()->where('serial', $request->tpa_serial)->exists()) {
            throw new ApiException(ErrorCode::TPA_ALREADY_LINKED, 422, 'This TPA is already linked to your account.');
        }

        $tpa = $customer->tpas()->create([
            'serial' => $request->tpa_serial,
            'status' => 'active',
        ]);

        $token = $customer->createToken('tpa_login_' . $tpa->serial)->plainTextToken;

        return TpaAuthResource::make([
            'token'   => $token,
            'context' => 'tpa',
            'user'    => $customer,
            'tpa'     => $tpa,
        ])->response()->setStatusCode(201);
    }
}

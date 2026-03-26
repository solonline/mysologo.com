<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\AuthService;
use App\Exceptions\ApiException;
use App\Http\Requests\Customer\RegisterCustomerCorporateRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    /**
     * Register Retail
     *
     * @unauthenticated
     */
    public function registerRetail(RegisterCustomerRequest $request)
    {
        $result = $this->authService->register($request->validated());
        \Log::info('olha', ['result' => $result]);
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Register Corporate
     *
     * @unauthenticated
     */
    public function registerCorporate(RegisterCustomerCorporateRequest $request)
    {
        $result = $this->authService->register(array_merge($request->validated(), ['type' => 'corporate']));
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Login
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login($request->validated());
            return AuthResource::make($result)->response()->setStatusCode(200);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], $e->status);
        }
    }

    public function loginWithPin(LoginPinRequest $request)
    {
        try {
            $result = $this->authService->loginWithPin($request->validated());
            return AuthResource::make($result)->response()->setStatusCode(200);
        } catch (ApiException $e) {
            return response()->json([
                'success' => false,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], $e->status);
        }
    }
}

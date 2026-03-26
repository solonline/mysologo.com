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
        $result = $this->authService->login($request->validated());
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    public function loginWithPin(LoginPinRequest $request)
    {
        $result = $this->authService->loginWithPin($request->validated());
        return AuthResource::make($result)->response()->setStatusCode(200);
    }
}

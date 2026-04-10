<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\AuthService;
use App\DTOs\LoginCustomerDTO;
use App\DTOs\LoginWithPinDTO;
use App\DTOs\RegisterCustomerDTO;
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
        $data = $request->validated() + ['type' => 'retail'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Register Corporate
     *
     * @unauthenticated
     */
    public function registerCorporate(RegisterCustomerCorporateRequest $request)
    {
        $data = $request->validated() + ['type' => 'corporate'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Login
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request)
    {
        $dto = LoginCustomerDTO::fromArray($request->validated());
        $result = $this->authService->login($dto);
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    public function loginWithPin(LoginPinRequest $request)
    {
        $dto = LoginWithPinDTO::fromArray($request->validated());
        $result = $this->authService->loginWithPin($dto);
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    public function logout()
    {
        $this->authService->logout(auth()->user());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}

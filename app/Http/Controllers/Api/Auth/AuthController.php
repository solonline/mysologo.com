<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Http\Resources\Auth\DiditSessionResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\DiditService;
use App\Services\KycService;
use App\Exceptions\ApiException;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(RegisterUserRequest $request)
    {
        return AuthResource::make($this->authService->register($request->validated()))->response()->setStatusCode(201);
    }

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

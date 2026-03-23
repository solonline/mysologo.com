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

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(RegisterUserRequest $request)
    {
        return AuthResource::make($this->authService->register($request->validated()))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());
        if (!$result) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    public function loginWithPin(LoginPinRequest $request)
    {
        $result = $this->authService->loginWithPin($request->validated());
        if (!isset($result['success']) || !$result['success']) {
            $status = 401;
            $message = 'Invalid credentials or PIN';
            if (isset($result['reason']) && $result['reason'] === 'device_blocked') {
                $status = 423;
                $message = $result['message'] ?? 'PIN blocked. Please perform a conventional login and create a new PIN for this device.';
            }
            return response()->json([
                'success' => false,
                'reason' => $result['reason'] ?? 'invalid',
                'message' => $message,
            ], $status);
        }
        return AuthResource::make($result)->response()->setStatusCode(200);
    }
}

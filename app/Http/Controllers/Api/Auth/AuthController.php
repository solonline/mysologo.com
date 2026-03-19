<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        return AuthResource::make((new AuthService())->register($request->validated()))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        $result = (new AuthService())->login($request->validated());
        if (!$result) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        return AuthResource::make($result)->response()->setStatusCode(200);
    }
}

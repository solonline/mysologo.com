<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use App\Jobs\SendPasswordResetEmail;
use App\Http\Resources\Auth\AuthResource;
use App\Services\AuthService;
use App\DTOs\LoginCustomerDTO;
use App\DTOs\LoginWithPinDTO;
use App\DTOs\RegisterCustomerDTO;
use App\Http\Requests\Customer\RegisterCustomerCorporateRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Services\SettingsService;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PasswordResetService $passwordResetService,
        private SettingsService $settingsService
    ) {}
    /**
     * Forgot Password
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->input('email');
        $token = $this->passwordResetService->createToken($email);
        if (!$token) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }
        SendPasswordResetEmail::dispatch($email, $token, $this->settingsService);
        return response()->json(['message' => 'E-mail de redefinição enviado.'], 200);
    }

    /**
     * Reset Password
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        $ok = $this->passwordResetService->resetPassword($data['email'], $data['token'], $data['password']);
        if (!$ok) {
            return response()->json(['message' => 'Token inválido ou expirado.', 'ok' => $ok], 422);
        }
        return response()->json(['message' => 'Senha redefinida com sucesso.'], 200);
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

    /**
     * Login with PIN
     * @unauthenticated
     */
    public function loginWithPin(LoginPinRequest $request)
    {
        $dto = LoginWithPinDTO::fromArray($request->validated());
        $result = $this->authService->loginWithPin($dto);
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    /*
    * Logout
    */
    public function logout()
    {
        $this->authService->logout(auth()->user());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}

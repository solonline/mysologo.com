<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LoginTpaRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use App\Jobs\SendPasswordResetEmail;
use App\Http\Resources\Auth\AuthResource;
use App\Http\Resources\Auth\TpaAuthResource;
use App\Services\AuthService;
use App\DTOs\LoginCustomerDTO;
use App\DTOs\LoginWithPinDTO;
use App\DTOs\LoginWithTpaDTO;
use App\DTOs\RegisterCustomerDTO;
use App\Http\Requests\Customer\RegisterCustomerCorporateRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PasswordResetService $passwordResetService,
        private SettingsService $settingsService
    ) {}

    /**
     * Forgot Password
     *
     * Sends a password reset link to the customer's registered email.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
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
     *
     * Resets the customer's password using the token received by email.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ok = $this->passwordResetService->resetPassword($data['email'], $data['token'], $data['password']);
        if (!$ok) {
            return response()->json(['message' => 'Token inválido ou expirado.', 'ok' => $ok], 422);
        }
        return response()->json(['message' => 'Senha redefinida com sucesso.'], 200);
    }

    /**
     * Register Retail Customer
     *
     * Creates a new individual customer account. Returns a bearer token and account details.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function registerRetail(RegisterCustomerRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $data = $request->validated() + ['type' => 'retail'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Register Corporate Customer
     *
     * Creates a new corporate account. KYC for the legal representative must be completed after registration via `POST /customer/create-corporate-responsible`.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function registerCorporate(RegisterCustomerCorporateRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $data = $request->validated() + ['type' => 'corporate'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);
        return AuthResource::make($result)->response()->setStatusCode(201);
    }

    /**
     * Login
     *
     * Authenticates a customer with email and password. Returns a bearer token.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function login(LoginRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $dto = LoginCustomerDTO::fromArray($request->validated());
        $result = $this->authService->login($dto);
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    /**
     * Login with PIN
     *
     * Authenticates a customer using a device token and 4-digit PIN. Requires a prior call to `POST /create-pin`.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function loginWithPin(LoginPinRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $dto = LoginWithPinDTO::fromArray($request->validated());
        $result = $this->authService->loginWithPin($dto);
        return AuthResource::make($result)->response()->setStatusCode(200);
    }

    /**
     * Login via TPA
     *
     * Authenticates a customer from a linked TPA terminal. The device must be linked first via `POST /tpa/link`.
     *
     * @tags Authentication
     * @unauthenticated
     */
    public function loginWithTpa(LoginTpaRequest $request): TpaAuthResource
    {
        $result = $this->authService->loginWithTpa(LoginWithTpaDTO::fromArray($request->validated()));

        return TpaAuthResource::make([
            'token'   => $result['token'],
            'context' => $result['context'],
            'user'    => $result['user'],
            'tpa'     => $result['tpa'],
        ]);
    }

    /**
     * Logout
     *
     * Revokes the current bearer token.
     *
     * @tags Authentication
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}

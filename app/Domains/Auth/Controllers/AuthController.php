<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\DTOs\LoginCustomerDTO;
use App\Domains\Auth\DTOs\LoginWithPinDTO;
use App\Domains\Auth\Jobs\SendPasswordResetEmail;
use App\Domains\Auth\Resources\AuthSessionResource;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\PasswordResetService;
use App\Domains\Customer\DTOs\RegisterCustomerDTO;
use App\Domains\Settings\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Customer\RegisterCustomerCorporateRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
     *
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $token = $this->passwordResetService->createToken($email);
        if (! $token) {
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
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ok = $this->passwordResetService->resetPassword($data['email'], $data['token'], $data['password']);
        if (! $ok) {
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
     *
     * @unauthenticated
     */
    public function registerRetail(RegisterCustomerRequest $request): Response|JsonResponse
    {
        $data = $request->validated() + ['type' => 'retail'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);

        return $this->respondWithAuth($result, 201);
    }

    /**
     * Register Corporate Customer
     *
     * Creates a new corporate account. KYC for the legal representative must be completed after registration via `POST /customer/create-corporate-responsible`.
     *
     * @tags Authentication
     *
     * @unauthenticated
     */
    public function registerCorporate(RegisterCustomerCorporateRequest $request): Response|JsonResponse
    {
        $data = $request->validated() + ['type' => 'corporate'];
        $dto = RegisterCustomerDTO::fromArray($data);
        $result = $this->authService->register($dto);

        return $this->respondWithAuth($result, 201);
    }

    /**
     * Login
     *
     * Authenticates a customer with email and password. Returns a bearer token.
     *
     * @tags Authentication
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): Response|JsonResponse
    {
        $dto = LoginCustomerDTO::fromArray($request->validated());
        $result = $this->authService->login($dto);

        return $this->respondWithAuth($result);
    }

    /**
     * Login with PIN
     *
     * Authenticates a customer using a device token and 4-digit PIN. Requires a prior call to `POST /create-pin`.
     *
     * @tags Authentication
     *
     * @unauthenticated
     */
    public function loginWithPin(LoginPinRequest $request): Response|JsonResponse
    {
        $dto = LoginWithPinDTO::fromArray($request->validated());
        $result = $this->authService->loginWithPin($dto);

        return $this->respondWithAuth($result);
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

    private function respondWithAuth(array $result, int $status = 200): Response|JsonResponse
    {
        return AuthSessionResource::make($result)->response()->setStatusCode($status);
    }
}

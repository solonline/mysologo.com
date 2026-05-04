<?php
namespace App\Services;

use App\Events\CustomerRegistered;
use App\Services\DeviceAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Contracts\CustomerRepositoryInterface;
use App\Contracts\DeviceRepositoryInterface;
use App\Contracts\StripeAccountRepositoryInterface;
use App\DTOs\CreateStripeAccountDTO;
use App\DTOs\LoginWithPinDTO;
use App\DTOs\RegisterCustomerDTO;
use App\DTOs\LoginCustomerDTO;
use App\Models\Customer;
use Illuminate\Support\Facades\URL;

class AuthService
{
    public function __construct(
        protected KycService $kycService,
        protected DeviceAuthService $deviceAuthService,
        protected CustomerRepositoryInterface $customerRepository,
        protected DeviceRepositoryInterface $deviceRepository,
        protected PhoneVerificationService $phoneVerificationService,
    ) {}

    public function register(RegisterCustomerDTO $dto)
    {
        try {
            $preparedDto = new RegisterCustomerDTO(
                email: $dto->email,
                password: Hash::make($dto->password),
                phone: $dto->phone,
                type: $dto->type,
                username: $dto->username ? strtolower(str_replace(' ', '_', $dto->username)) : null,
                name: $dto->name,
            );

            $customer = DB::transaction(function () use ($preparedDto) {
                $customer = $this->customerRepository->createCustomer($preparedDto);
                $settingsService = app(\App\Services\SettingsService::class);
                $infoSystem = $settingsService->getInfoSystem();
                $customerService = app(\App\Services\CustomerService::class);
                $validationUrl = $customerService->generateEmailValidationUrl($customer);
                CustomerRegistered::dispatch($customer, $validationUrl, $infoSystem);
                return $customer;
            });

            return $this->login(new LoginCustomerDTO(
                email: $customer->email,
                password: $dto->password
            ), true);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Erro inesperado ao registrar usuário.',
                'authRegister',
                $e
            );
        }
    }

    // Removido: toda lógica de mapeamento fica no DTO

    public function login(LoginCustomerDTO $dto, bool $isRegister = false)
    {
        $customer = $this->customerRepository->findByEmail($dto->email);

        if (!$customer || (!$isRegister && !Hash::check($dto->password, $customer->password))) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                401,
                'Invalid credentials.'
            );
        }

        $token = $customer->createToken('password_login', [], now()->addMinutes(10))->plainTextToken;
        $validatedEmail = $customer->hasVerifiedEmail();
        $validatedDocument = $customer->doc_status;

        // Valores padrão
        $diditSession = null;
        $requiresResponsible = false;
        $kycRequired = false;
        $message = null;

        if ($customer->type === 'corporate') {
            $responsible = $customer->responsibles()->first();
            if (is_null($responsible)) {
                $requiresResponsible = true;
                $kycRequired = false;
            } else {
                $diditSession = $responsible->diditSessions()->latest()->first();
                $kycRequired = $customer->doc_status;
                if (!$diditSession) {
                    $message = 'No Didit session found for responsible. Please complete KYC.';
                }
                $diditSession = $diditSession ? $diditSession->toArray() : null;
            }
        } elseif ($customer->type === 'retail') {
            $kyc = $this->kycService->ensureValidIndividualDocument($customer);
            \Log::info('KYC check for retail customer', ['customer_id' => $customer->id, 'kyc_result' => $kyc]);
            $diditSession = $kyc['session'] ?? null;
            $kycRequired = $customer->doc_status;
            $message = $kyc['message'] ?? null;
        }

        return [
            'user' => $customer,
            'token' => $token,
            'didit_session' => $diditSession,
            'requires_responsible' => $requiresResponsible,
            'kyc_status' => $kycRequired,
            'email_verified' => $validatedEmail,
            'document_verified' => $validatedDocument,
            'phone_verified' => $this->phoneVerificationService->isVerified($customer, $customer->phone),
            'message' => $message,
        ];
    }

    public function loginWithPin(LoginWithPinDTO $dto)
    {
        $device = $this->deviceRepository->findByTokenAndEmail($dto->deviceToken, $dto->email);

        if (!$device || !$device->pin) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Device not found or without PIN.'
            );
        }

        $customer = $device->customer;

        if ($this->deviceAuthService->isBlocked($device)) {
            throw new ApiException(
                ErrorCode::DEVICE_BLOCKED,
                423,
                'PIN blocked. Do login conventional to reset PIN.'
            );
        }

        if (!$customer || !Hash::check($dto->pin, $device->pin)) {
            $this->deviceAuthService->registerFailedAttempt($device);
            $this->deviceRepository->save($device);
            throw new ApiException(
                ErrorCode::INVALID_PIN,
                401,
                'Invalid PIN.'
            );
        }

        $this->deviceAuthService->resetAttempts($device);
        $device->last_used_at = now();
        $this->deviceRepository->save($device);

        $token = $customer->createToken('device_pin_login_' . ($device->device_name ?? $device->id), [], now()->addMinutes(10))->plainTextToken;

        return [
            'user' => $customer,
            'token' => $token,
            'didit_session' => ['status' => $customer->diditSessions()->latest()->first()?->status],
        ];
    }

    public function logout(Customer $customer)
    {
        $customer->tokens()->delete();
    }

    /**
     * Gera o link de validação de e-mail para o cliente.
     */
    private function generateEmailValidationUrl(Customer $customer): string
    {
        // Gera a URL assinada do backend
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $customer->id,
                'hash' => sha1($customer->email),
            ]
        );

        // Pega a URL do frontend do .env
        $frontendBase = env('URL_FRONTEND');

        if (!$frontendBase) {
            // fallback para APP_URL se não houver URL_FRONTEND
            $frontendBase = config('app.url');
        }
        // Extrai os parâmetros da URL assinada
        $parsed = parse_url($signedUrl);
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';

        // Extrai id e hash do path
        $matches = [];
        preg_match('#/email/verify/(\d+)/(\w+)#', $path, $matches);
        $id = $matches[1] ?? null;
        $hash = $matches[2] ?? null;

        // Monta a URL do frontend
        $frontendUrl = rtrim($frontendBase, '/') . "//email/verify/$id/$hash";
        if ($query) {
            $frontendUrl .= '?' . $query;
        }
        return $frontendUrl;
    }
}

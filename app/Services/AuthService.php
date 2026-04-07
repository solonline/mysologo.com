<?php
namespace App\Services;

use App\Events\CustomerRegistered;
use App\Services\DeviceAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Contracts\CustomerRepositoryInterface;
use App\DTOs\RegisterCustomerDTO;
use App\DTOs\LoginCustomerDTO;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\URL;


class AuthService
{
    public function __construct(
        protected KycService $kycService,
        protected DeviceAuthService $deviceAuthService,
        protected CustomerRepositoryInterface $customerRepository
    ) {}

    public function register(RegisterCustomerDTO $dto)
    {
        try {
            $customer = DB::transaction(function () use ($dto) {
                $customer = $this->customerRepository->createCustomer($dto);
                // Aqui você pode adicionar lógica para documentos corporativos, se necessário
                CustomerRegistered::dispatch($customer, $this->generateEmailValidationUrl($customer));
                return $customer;
            });

            // Após criar, faz login (que já retorna status do Didit)
            return $this->login(new LoginCustomerDTO(
                email: $customer->email,
                password: $dto->password // já está hasheada, mas login espera plain, então ajuste se necessário
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
        // Aqui você pode usar o repository se quiser customizar a busca do customer
        $customer = Customer::where('email', $dto->email)->first();

        if (!$customer || (!$isRegister && !Hash::check($dto->password, $customer->password))) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                401,
                'Invalid credentials.'
            );
        }

        $token = $customer->createToken('password_login', [], now()->addMinutes(10))->plainTextToken;
        $validatedEmail = $customer->hasVerifiedEmail();
        $validatedDocument = $customer->corporateDocuments()->where('status', 'approved')->exists();

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
                $kycRequired = true;
                if (!$diditSession) {
                    $message = 'No Didit session found for responsible. Please complete KYC.';
                }
                $diditSession = $diditSession ? $diditSession->toArray() : null;
            }
        } elseif ($customer->type === 'retail') {
            $kyc = $this->kycService->ensureValidIndividualDocument($customer);
            $diditSession = $kyc['session'] ?? null;
            $kycRequired = true;
            $message = $kyc['message'] ?? null;
        }

        return [
            'user' => $customer,
            'token' => $token,
            'didit_session' => $diditSession,
            'requires_responsible' => $requiresResponsible,
            'kyc_required' => $kycRequired,
            'email_verified' => $validatedEmail,
            'document_verified' => $validatedDocument,
            'message' => $message,
        ];
    }

    public function loginWithPin(array $data)
    {
        $device = CustomerDevice::where('device_token', $data['device_token'])
            ->whereHas('customer', fn($q) => $q->where('email', $data['email']))
            ->first();
        if (!$device || !$device->pin) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Device not found or without PIN.'
            );
        }

        $customer = $device->customer;

        // If device is blocked, require conventional login to reset PIN
        if ($this->deviceAuthService->isBlocked($device)) {
            throw new ApiException(
                ErrorCode::DEVICE_BLOCKED,
                423,
                'PIN blocked. Do login conventional to reset PIN.'
            );
        }

        if (!$customer || !$device->trusted || !Hash::check($data['pin'], $device->pin)) {
            $this->deviceAuthService->registerFailedAttempt($device);
            throw new ApiException(
                ErrorCode::INVALID_PIN,
                401,
                'Invalid PIN.'
            );
        }

        // Sucesso: reseta tentativas
        $this->deviceAuthService->resetAttempts($device);
        $device->last_used_at = now();
        $device->save();

        $token = $customer->createToken('device_pin_login_' . ($device->device_name ?? $device->id), [], now()->addMinutes(10))->plainTextToken;

        return [
            'user' => $customer,
            'token' => $token,
            'didit_session' => ['status' => $customer->diditSessions()->latest()->first()?->status],
        ];
    }

    /**
     * Gera o link de validação de e-mail para o cliente.
     */
    private function generateEmailValidationUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $customer->id,
                'hash' => sha1($customer->email),
            ]
        );
    }
}

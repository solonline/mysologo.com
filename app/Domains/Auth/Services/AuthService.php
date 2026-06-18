<?php

namespace App\Domains\Auth\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Auth\Contracts\DeviceRepositoryInterface;
use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Onboarding\Services\OnboardingStatusResolver;
use App\Domains\Auth\DTOs\LoginCustomerDTO;
use App\Domains\Auth\DTOs\LoginWithPinDTO;
use App\Domains\Auth\DTOs\LoginWithTpaDTO;
use App\Domains\Customer\DTOs\RegisterCustomerDTO;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Settings\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected OnboardingStatusResolver $onboardingStatusResolver,
        protected DeviceAuthService $deviceAuthService,
        protected CustomerRepositoryInterface $customerRepository,
        protected DeviceRepositoryInterface $deviceRepository,
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
                language: $dto->language,
                currency: $dto->currency
            );

            $customer = DB::transaction(function () use ($preparedDto) {
                $customer = $this->customerRepository->createCustomer($preparedDto);
                $infoSystem = app(SettingsService::class)->getInfoSystem();
                $validationUrl = app(CustomerProfileService::class)->generateEmailValidationUrl($customer);
                UserRegistered::dispatch($customer, $validationUrl, $infoSystem);

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

        return [
            'user' => $customer,
            'token' => $token,
            'onboarding_status' => $this->onboardingStatusResolver->resolveForCustomer($customer),
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
            'onboarding_status' => $this->onboardingStatusResolver->resolveForPinLogin($customer),
        ];
    }

    public function loginWithTpa(LoginWithTpaDTO $dto): array
    {
        $customer = $this->customerRepository->findByEmail($dto->email);

        if (!$customer || !Hash::check($dto->password, $customer->password)) {
            throw new ApiException(ErrorCode::INVALID_CREDENTIALS, 401, 'Invalid credentials.');
        }

        $tpa = $customer->tpas()->where('serial', $dto->tpaSerial)->first();

        if (!$tpa) {
            throw new ApiException(ErrorCode::TPA_NOT_FOUND, 404, 'TPA not found for this customer.');
        }

        if ($tpa->status !== 'active') {
            throw new ApiException(ErrorCode::TPA_NOT_ACTIVE, 403, 'TPA is not active.');
        }

        $token = $customer->createToken('tpa_login_' . $tpa->serial)->plainTextToken;

        return [
            'user'    => $customer,
            'token'   => $token,
            'context' => 'tpa',
            'tpa'     => $tpa,
        ];
    }

    public function logout(Customer $customer)
    {
        $customer->tokens()->delete();
    }
}

<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\DeviceAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\CustomerDevice;

class AuthService
{
    public function __construct(protected KycService $kycService, protected DeviceAuthService $deviceAuthService)
    {

    }
    public function register(array $data)
    {
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);

        $customerFillable = (new Customer())->getFillable();
        $customerData = array_intersect_key($data, array_flip($customerFillable));

        try {
            $customer = DB::transaction(function () use ($customerData, $data) {
                $customer = Customer::create($customerData);

                return $customer;
            });
            return $this->login([
                'email' => $customer->email,
                'password' => $plainPassword,
            ], true);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }

            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                $e->getMessage(),
                'authRegister' // evita duplicidade de log
            );
        }
    }

    public function login(array $data, bool $isRegister = false)
    {
        $customer = Customer::where('email', $data['email'])->first();

        if (!$customer || (!$isRegister && !Hash::check($data['password'], $customer->password))) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                401,
                'Invalid credentials.'
            );
        }

        $token = $customer->createToken('password_login', [], now()->addMinutes(10))->plainTextToken;

        // 🔴 CORPORATE FLOW (sempre checa estado)
        if ($customer->type === 'corporate') {

            $responsible = $customer->responsibles()->exists();

            // ❌ não tem responsável
            if (!$responsible) {
                return [
                    'user' => $customer,
                    'token' => $token,
                    'didit_session' => null,
                    'requires_responsible' => true,
                    'kyc_required' => true,
                ];
            }

            // 🔵 tem responsável → verificar sessão Didit
            $diditSession = $responsible->diditSessions()->latest()->first();

            return [
                'user' => $customer,
                'token' => $token,
                'didit_session' => $diditSession,
                'requires_responsible' => false,
                'kyc_required' => true,
            ];
        }

        // 🔵 RETAIL FLOW
        if ($customer->type === 'retail') {

            if ($isRegister) {
                $this->kycService->ensureValidIndividualDocument($customer);
            }

            $diditSession = $customer->diditSessions()->latest()->first();

            return [
                'user' => $customer,
                'token' => $token,
                'didit_session' => $diditSession,
                'kyc_required' => true,
            ];
        }
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
}

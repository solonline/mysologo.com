<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Providers\DiditProvider;
use App\Services\DeviceAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(protected KycService $kycService, protected DeviceAuthService $deviceAuthService)
    {

    }
    public function register(array $data)
    {
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);

        $userFillable = (new User())->getFillable();
        $userData = array_intersect_key($data, array_flip($userFillable));

        try {
            $user = DB::transaction(function () use ($userData, $data) {
                $user = User::create($userData);
                $role = isset($data['role'])
                    ? Role::findByName($data['role'], 'api')
                    : Role::findByName('retail_customer', 'api');
                if ($role) {
                    $user->assignRole($role);
                }
                return $user;
            });
            return $this->login([
                'email' => $user->email,
                'password' => $plainPassword,
            ], true);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }

            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Error registering user.',
                'authRegister' // evita duplicidade de log
            );
        }
    }

    public function login(array $data, bool $isRegister = false)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || (!$isRegister && !Hash::check($data['password'], $user->password))) {
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                401,
                'Invalid credentials.'
            );
        }

        $token = $user->createToken('password_login', [], now()->addMinutes(10))->plainTextToken;

        $this->kycService->ensureValidSession($user);

        // Garante que diditSession existe antes de acessar refresh
        $diditSession = $user->diditSession()->latest()->first();

        return [
            'user' => $user,
            'token' => $token,
            'didit_session' => $diditSession,
        ];
    }

    public function loginWithPin(array $data)
    {
        $device = UserDevice::where('device_token', $data['device_token'])
            ->whereHas('user', fn($q) => $q->where('email', $data['email']))
            ->first();
        if (!$device || !$device->pin) {
            throw new ApiException(
                ErrorCode::DEVICE_NOT_FOUND,
                404,
                'Device not found or without PIN.'
            );
        }

        $user = $device->user;

        // If device is blocked, require conventional login to reset PIN
        if ($this->deviceAuthService->isBlocked($device)) {
            throw new ApiException(
                ErrorCode::DEVICE_BLOCKED,
                423,
                'PIN blocked. Do login conventional to reset PIN.'
            );
        }

        if (!$user || !$device->trusted || !Hash::check($data['pin'], $device->pin)) {
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

        $token = $user->createToken('device_pin_login_' . ($device->device_name ?? $device->id), [], now()->addMinutes(10))->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'didit_session' => ['status' => $user->diditSession?->status],
        ];
    }
}

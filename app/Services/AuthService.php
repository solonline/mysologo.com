<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Providers\DiditProvider;
use App\Services\DeviceAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function __construct(protected KycService $kycService, protected DeviceAuthService $deviceAuthService)
    {

    }
    public function register(array $data)
    {
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);

        // Filtra apenas campos permitidos pelo modelo User
        $userFillable = (new User())->getFillable();
        $userData = array_intersect_key($data, array_flip($userFillable));

        try {
            DB::beginTransaction();
            $user = User::create($userData);

            // Atribui role se existir
            $role = isset($data['role']) ? Role::findByName($data['role'], 'api') : Role::findByName('retail_customer', 'api');
            if ($role) {
                $user->assignRole($role);
            }


            $loginData = $this->login([
                'email' => $user->email,
                'password' => $plainPassword,
            ], true);
            DB::commit();

            return $loginData;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function login(array $data, bool $isRegister = false)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return null;
        }

        if (!$isRegister && !Hash::check($data['password'], $user->password)) {
            return null;
        }

        $token = $user->createToken('password_login', [], now()->addMinutes(10))->plainTextToken;

        $this->kycService->ensureValidSession($user);

        // Garante que diditSession existe antes de acessar refresh
        $diditSession = $user->diditSession()->latest()->first();
        return [
            'user' => $user,
            'token' => $token,
            'didit_session' => $diditSession ? $diditSession : null,
        ];
    }

    public function loginWithPin(array $data)
    {
        $device = UserDevice::where('device_token', $data['device_token'])
            ->whereHas('user', fn($q) => $q->where('email', $data['email']))
            ->first();
        if (!$device || !$device->pin) {
            return [
                'success' => false,
                'reason' => 'device_not_found_or_no_pin',
            ];
        }

        $user = $device->user;

        // Se device está bloqueado, exige login convencional para resetar PIN
        if ($this->deviceAuthService->isBlocked($device)) {
            return [
                'success' => false,
                'reason' => 'device_blocked',
                'message' => 'PIN bloqueado. Faça login convencional e crie um novo PIN para este dispositivo.'
            ];
        }

        if (!$user || !$device->trusted || !Hash::check($data['pin'], $device->pin)) {
            $this->deviceAuthService->registerFailedAttempt($device);
            return [
                'success' => false,
                'reason' => 'invalid_pin',
            ];
        }

        // Sucesso: reseta tentativas
        $this->deviceAuthService->resetAttempts($device);
        $device->last_used_at = now();
        $device->save();

        $token = $user->createToken('device_pin_login_' . ($device->device_name ?? $device->id), [], now()->addMinutes(10))->plainTextToken;

        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
            'didit_session' => null,
        ];
    }
}

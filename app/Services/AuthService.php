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

        // Mapper: transforma o array conforme tipo
        [$customerData, $documentData] = $this->mapCustomerData($data);

        try {
            $customer = DB::transaction(function () use ($customerData, $documentData) {
                $customer = Customer::create($customerData);

                if (!empty($documentData)) {
                    $customer->corporateDocuments()->create($documentData);
                }

                return $customer;
            });

            // Após criar, faz login (que já retorna status do Didit)
            return $this->login([
                'email' => $customer->email,
                'password' => $plainPassword,
            ], true);
        } catch (\Throwable $e) {
            // Sempre propaga ApiException original
            if ($e instanceof ApiException) {
                throw $e;
            }
            // Só lança ApiException genérica para erro inesperado
            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                500,
                'Erro inesperado ao registrar usuário.',
                'authRegister'
            );
        }
    }

    protected function mapCustomerData(array $data): array
    {
        $customerFillable = (new Customer())->getFillable();
        $customerData = array_intersect_key($data, array_flip($customerFillable));

        $documentData = [];
        if (!empty($data['type']) && $data['type'] === 'corporate') {
            $customerData['type'] = 'corporate';
            $customerData['name'] = $data['legal_name'];
            $customerData['username'] = str_replace(' ', '_', strtolower($data['trading_name'])); // ou algum outro campo que faça sentido

            $documentFillable = (new \App\Models\CustomerCorporateDocument())->getFillable();
            $documentData = array_intersect_key($data, array_flip($documentFillable));
        }

        return [$customerData, $documentData];
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

            $responsible = $customer->responsibles()->first();

            // ❌ não tem responsável
            if (is_null($responsible)) {
                return [
                    'user' => $customer,
                    'token' => $token,
                    'didit_session' => null,
                    'requires_responsible' => true,
                ];
            }

            // 🔵 tem responsável → verificar sessão Didit
            $diditSession = $responsible->diditSessions()->latest()->first();
            \Log::info('Corporate login - responsible Didit session', [
                'customer_id' => $customer->id,
                'responsible_id' => $responsible->id,
                'session' => $diditSession ? $diditSession->toArray() : null,
            ]);
            return [
                'user' => $customer,
                'token' => $token,
                'didit_session' => $diditSession ? $diditSession->toArray() : null,
                'requires_responsible' => false,
                'kyc_required' => true,
                'message' => $diditSession ? null : 'No Didit session found for responsible. Please complete KYC.',
            ];
        }

        // 🔵 RETAIL FLOW
        if ($customer->type === 'retail') {
            $kyc = $this->kycService->ensureValidIndividualDocument($customer);

            return [
                'user' => $customer,
                'token' => $token,
                'didit_session' => $kyc['session'],
                'didit_status' => $kyc['status'],
                'kyc_required' => true,
                'message' => $kyc['message'] ?? null,
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

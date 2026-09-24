<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgetDeviceRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceController extends Controller
{
    public function __construct(
        private DeviceAuthService $deviceAuthService,
    ) {}

    /**
     * Forget device
     *
     * Deletes this device, including its PIN and push token, and revokes the current bearer token.
     *
     * @tags Authentication
     */
    public function destroy(ForgetDeviceRequest $request): JsonResponse
    {
        $customer = $request->user();

        $this->deviceAuthService->forgetDevice(
            $customer,
            $request->string('device_token')->toString(),
        );

        $accessToken = $customer->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return response()->json(['ok' => true]);
    }
}

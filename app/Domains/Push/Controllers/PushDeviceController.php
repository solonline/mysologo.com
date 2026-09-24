<?php

namespace App\Domains\Push\Controllers;

use App\Domains\Push\Http\Requests\DisablePushDeviceRequest;
use App\Domains\Push\Http\Requests\RegisterPushDeviceRequest;
use App\Domains\Push\Services\PushDeviceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PushDeviceController extends Controller
{
    public function __construct(
        protected PushDeviceService $devices,
    ) {}

    /**
     * Register push device
     *
     * Stores the FCM token for an existing device. Push is enabled only when a token is sent.
     *
     * @tags Push
     */
    public function update(RegisterPushDeviceRequest $request): JsonResponse
    {
        $this->devices->register(
            $request->user(),
            $request->string('device_token')->toString(),
            $request->string('platform')->toString(),
            $request->input('push_token'),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Disable push device
     *
     * Clears the FCM token and turns push off. The device PIN is kept.
     *
     * @tags Push
     */
    public function destroy(DisablePushDeviceRequest $request): JsonResponse
    {
        $this->devices->disable(
            $request->user(),
            $request->string('device_token')->toString(),
        );

        return response()->json(['ok' => true]);
    }
}

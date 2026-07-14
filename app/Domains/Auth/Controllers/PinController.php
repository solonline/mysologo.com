<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\DTOs\CreatePinDTO;
use App\Domains\Auth\DTOs\ResetPinDTO;
use App\Domains\Auth\Services\DeviceAuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreatePinRequest;
use App\Http\Requests\Auth\ResetPinRequest;

class PinController extends Controller
{
    public function __construct(private DeviceAuthService $deviceAuthService) {}

    /**
     * Create PIN
     *
     * Registers a 4-digit PIN for a device, enabling fast PIN-based login via `POST /login-pin`.
     *
     * @tags Authentication
     */
    public function store(CreatePinRequest $request)
    {
        $dto = CreatePinDTO::fromArray($request->validated());
        $this->deviceAuthService->createPin($request->user(), $dto);

        return response()->json(['message' => 'PIN created successfully'], 201);
    }

    /**
     * Reset PIN
     *
     * Changes the device PIN after verifying the current PIN or account password.
     *
     * @tags Authentication
     */
    public function reset(ResetPinRequest $request)
    {
        $dto = ResetPinDTO::fromArray($request->validated());
        $this->deviceAuthService->resetPin($request->user(), $dto);

        return response()->json(['message' => 'PIN updated successfully']);
    }
}

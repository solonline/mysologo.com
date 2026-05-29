<?php

namespace App\Http\Controllers\Api\Auth;

use App\DTOs\CreatePinDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreatePinRequest;
use App\Services\DeviceAuthService;

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
}

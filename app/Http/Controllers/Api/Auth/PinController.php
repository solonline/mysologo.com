<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreatePinRequest;
use Illuminate\Support\Facades\Hash;

class PinController extends Controller
{
    public function store(CreatePinRequest $request)
    {
        $customer = $request->user();

        $customer->devices()->updateOrCreate(
            ['device_token' => $request->input('device_token')],
            [
                'device_name' => $request->input('device_name'),
                'pin' => Hash::make($request->input('pin'))
            ]
        );
        return response()->json(['message' => 'PIN created successfully'], 201);
    }
}

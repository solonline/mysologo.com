<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\SmsValidationResource;
use App\Services\PhoneVerificationService;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationController extends Controller
{
    protected PhoneVerificationService $service;

    public function __construct(PhoneVerificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Send Phone Verification Code
     *
     * Sends a one-time SMS code to the customer's registered phone number.
     *
     * @tags Phone Verification
     */
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        /** @var Customer $customer */
        $customer = Auth::user();
        $sent = $this->service->sendSms($customer, $customer->phone);

        $msg = $sent ? 'Verification code sent.' : 'Phone already verified.';
        return SmsValidationResource::make([
            'message' => $msg,
            'status' => $sent ? 'success' : 'failed',
        ]);
    }

    /**
     * Confirm Phone Verification Code
     *
     * Validates the SMS code and marks the phone number as verified.
     *
     * @tags Phone Verification
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        /** @var Customer $customer */
        $customer = Auth::user();
        $valid = $this->service->verify($customer, $customer->phone, $request->input('code'));

        if ($valid) {
            return SmsValidationResource::make([
                'message' => 'Phone successfully verified.',
                'status' => 'success',
            ]);
        }
        return SmsValidationResource::make([
            'message' => 'Invalid verification code.',
            'status' => 'failed',
        ])->response()->setStatusCode(422);
    }
}

<?php

namespace App\Domains\Onboarding\Controllers;

use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Sms\Support\SmsLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\SmsValidationResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationController extends Controller
{
    public function __construct(protected PhoneVerificationService $service) {}

    /**
     * Send Phone Verification Code
     *
     * Sends a one-time SMS code to the customer's registered phone number.
     *
     * @tags Onboarding
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

        SmsLogger::info('onboarding_phone_send_endpoint', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'sent' => $sent,
            'message' => $msg,
        ]);

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
     * @tags Onboarding
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

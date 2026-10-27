<?php

namespace App\Domains\Onboarding\Controllers;

use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Sms\Support\SmsLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\SendOnboardingPhoneRequest;
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
     * Sends a one-time code to the customer's phone. Optional `phone` corrects
     * an unverified number from registration before sending. Optional `whatsapp`
     * sends the code via WhatsApp instead of SMS.
     *
     * @tags Onboarding
     */
    public function send(SendOnboardingPhoneRequest $request)
    {
        /** @var Customer $customer */
        $customer = Auth::user();
        $viaWhatsapp = $request->boolean('whatsapp');
        $sent = $this->service->sendForOnboarding(
            $customer,
            $request->validated('phone'),
            $viaWhatsapp,
        );
        $customer->refresh();

        if ($sent) {
            $msg = 'Verification code sent.';
        } elseif ($this->service->isVerified($customer, (string) $customer->phone)) {
            $msg = 'Phone already verified.';
        } else {
            $msg = 'Unable to send verification code.';
        }

        SmsLogger::info('onboarding_phone_send_endpoint', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'channel' => $viaWhatsapp ? 'whatsapp' : 'sms',
            'sent' => $sent,
            'message' => $msg,
        ]);

        $resource = SmsValidationResource::make([
            'message' => $msg,
            'status' => $sent ? 'success' : 'failed',
            'phone' => $customer->phone,
        ]);

        if ($sent || $this->service->isVerified($customer, (string) $customer->phone)) {
            return $resource;
        }

        return $resource->response()->setStatusCode(424);
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

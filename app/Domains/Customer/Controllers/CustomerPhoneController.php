<?php

namespace App\Domains\Customer\Controllers;

use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ChangeCustomerPhoneRequest;
use App\Http\Requests\Customer\ConfirmCustomerPhoneRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PhoneChangeResource;
use Illuminate\Http\JsonResponse;

class CustomerPhoneController extends Controller
{
    public function __construct(
        protected CustomerPhoneChangeService $phoneChange,
    ) {}

    /**
     * Request Phone Change
     *
     * Sends an SMS code to the new number. The phone is only updated after confirmation.
     *
     * @tags Profile
     */
    public function requestChange(ChangeCustomerPhoneRequest $request): PhoneChangeResource
    {
        $result = $this->phoneChange->requestChange(
            $request->user(),
            $request->validated('phone'),
        );

        return PhoneChangeResource::make($result);
    }

    /**
     * Confirm Phone Change
     *
     * Validates the SMS code sent to the new phone number and applies the change.
     *
     * @tags Profile
     */
    public function confirmChange(ConfirmCustomerPhoneRequest $request): JsonResponse
    {
        $customer = $this->phoneChange->confirmChange(
            $request->user(),
            $request->validated('phone'),
            $request->validated('code'),
        );

        return response()->json([
            'message' => 'Phone number updated successfully.',
            'customer' => CustomerResource::make($customer),
        ]);
    }
}

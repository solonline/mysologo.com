<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class EmailValidationController extends Controller
{
    public function __construct(
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function verifyFrontend(Request $request, $id, $hash)
    {
        if (!$id || !$hash) {
            return response()->json(['message' => 'Invalid parameters'], 400);
        }

        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if (sha1($customer->email) !== $hash) {
            return response()->json(['message' => 'Invalid hash'], 400);
        }
        if ($customer->hasVerifiedEmail()) {
            return response()->json(['status' => 'already_verified', 'name' => $customer->name], 409);
        }
        $customer->markEmailAsVerified();

        if ($this->lifecycle->current($customer) === CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION) {
            $this->lifecycle->transitionTo($customer, CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION);
        }

        return response()->json(['status' => 'success', 'name' => $customer->name]);
    }
}

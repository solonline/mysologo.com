<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailValidationController extends Controller
{
    public function __construct(
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function verifyFrontend(Request $request, $id, $hash): Response|JsonResponse
    {
        if (! $id || ! $hash) {
            return $this->renderResult($request, 'invalid', null, 400);
        }

        $customer = Customer::find($id);

        if (! $customer) {
            return $this->renderResult($request, 'not_found', null, 404);
        }

        if (sha1($customer->email) !== $hash) {
            return $this->renderResult($request, 'invalid_hash', null, 400);
        }

        if ($customer->hasVerifiedEmail()) {
            return $this->renderResult($request, 'already_verified', $customer->name, 409);
        }

        $customer->markEmailAsVerified();

        if ($this->lifecycle->current($customer) === CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION) {
            $this->lifecycle->transitionTo($customer, CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION);
        }

        return $this->renderResult($request, 'success', $customer->name, 200);
    }

    private function renderResult(Request $request, string $status, ?string $name, int $httpStatus): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->jsonResult($status, $name, $httpStatus);
        }

        return response()->view('auth.email-verification-result', [
            'status' => $status,
            'name' => $name,
        ], $httpStatus);
    }

    private function jsonResult(string $status, ?string $name, int $httpStatus): JsonResponse
    {
        return match ($status) {
            'success' => response()->json(['status' => 'success', 'name' => $name], $httpStatus),
            'already_verified' => response()->json(['status' => 'already_verified', 'name' => $name], $httpStatus),
            'not_found' => response()->json(['message' => 'User not found'], $httpStatus),
            'invalid_hash' => response()->json(['message' => 'Invalid hash'], $httpStatus),
            default => response()->json(['message' => 'Invalid parameters'], $httpStatus),
        };
    }
}

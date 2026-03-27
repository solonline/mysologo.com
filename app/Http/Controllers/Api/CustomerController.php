<?php

namespace App\Http\Controllers\Api;

use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct() {}

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        // return $this->customerService->update($request->all());
    }

    public function createResponsible(Request $request)
    {
        // return $this->customerService->updateOrCreateResponsible($request->user(), $request->all());
    }

    public function submitCorporateDocuments(Request $request)
    {
        // return $this->customerService->uploadDocument($request->user(), $request->all());
    }

    public function sendValidationEmail(Request $request)
    {
        // $customer = $request->user();
        // $validationUrl = $this->customerService->generateEmailValidationUrl($customer);
        // $this->customerService->sendValidationEmail($customer, $validationUrl);
        return response()->json(['message' => 'Validation email sent.']);
    }
}
